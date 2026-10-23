import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

const temp = mkdtempSync(path.join(tmpdir(), 'rasta-browser-'));
const php = process.env.PHP_BINARY || 'php';
const memberPin = '2468';
const adminPin = '8642';
writeFileSync(path.join(temp, 'database.sqlite'), '');

const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(temp, 'database.sqlite'),
    SESSION_DRIVER: 'cookie',
    CACHE_STORE: 'array',
    RASTA_TEST_MEMBER_PIN: memberPin,
    RASTA_TEST_ADMIN_PIN: adminPin,
};

const migrate = spawnSync(php, ['artisan', 'migrate', '--force'], { env, stdio: 'pipe' });
if (migrate.status !== 0) {
    process.stderr.write(migrate.stdout);
    process.stderr.write(migrate.stderr);
    throw Error('Isolated migration failed');
}

const seed = spawnSync(php, ['tests/Browser/seed-workspace.php'], { env, stdio: 'pipe' });
if (seed.status !== 0) {
    process.stderr.write(seed.stdout);
    process.stderr.write(seed.stderr);
    throw Error('Isolated browser fixture setup failed');
}

const server = spawn(php, ['-S', '127.0.0.1:0', '-t', 'public'], {
    env,
    stdio: ['ignore', 'pipe', 'pipe'],
});
let serverOutput = '';
let serverStarted = false;
try {
    const serverUrl = await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(Error(`Browser server startup timed out: ${serverOutput}`)), 5000);
        const onOutput = (chunk) => {
            if (serverStarted) return;
            serverOutput += chunk.toString();
            const match = /Development Server \(http:\/\/127\.0\.0\.1:(\d+)\) started/.exec(serverOutput);
            if (!match) return;
            serverStarted = true;
            clearTimeout(timeout);
            resolve(`http://127.0.0.1:${match[1]}`);
        };

        server.stdout.on('data', onOutput);
        server.stderr.on('data', onOutput);
        server.once('error', (error) => {
            clearTimeout(timeout);
            reject(error);
        });
        server.once('exit', (code) => {
            if (serverStarted) return;
            clearTimeout(timeout);
            reject(Error(`Browser server exited before startup (${code}): ${serverOutput}`));
        });
    });

    env.RASTA_TEST_BASE_URL = serverUrl;

    let ready = false;
    let lastError = 'No response';
    for (let i = 0; i < 50; i++) {
        try {
            const response = await fetch(`${serverUrl}/login`);
            if (response.ok) {
                ready = true;
                break;
            }
            lastError = `HTTP ${response.status}`;
        } catch (error) {
            lastError = error.message;
        }
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
    if (!ready) throw Error(`Browser server did not become ready: ${lastError}`);

    const child = spawn(process.execPath, ['node_modules/@playwright/test/cli.js', 'test'], { env, stdio: 'inherit' });
    const code = await new Promise((resolve, reject) => {
        child.on('error', reject);
        child.on('exit', resolve);
    });
    process.exitCode = code ?? 1;
} finally {
    server.kill('SIGTERM');
    rmSync(temp, { recursive: true, force: true });
}
