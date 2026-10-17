import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

const root = path.resolve(import.meta.dirname, '..');
const tokensDir = path.join(root, 'tokens', 'color');
const buildScript = path.join(root, 'scripts', 'build-color-tokens.mjs');

let timer;
let running = false;
let pending = false;

function runBuild() {
  if (running) {
    pending = true;
    return;
  }

  running = true;
  const child = spawn(process.execPath, [buildScript, '--dev'], {
    cwd: root,
    stdio: 'inherit',
  });

  child.on('exit', (code) => {
    running = false;
    if (code !== 0) console.error(`[tokens] build failed with exit code ${code}`);
    if (pending) {
      pending = false;
      runBuild();
    }
  });
}

function scheduleBuild(filename) {
  if (filename && !filename.endsWith('.json')) return;
  clearTimeout(timer);
  timer = setTimeout(() => {
    console.log(`[tokens] ${filename || 'token file'} changed; rebuilding...`);
    runBuild();
  }, 120);
}

console.log(`[tokens] watching ${tokensDir}`);
runBuild();
fs.watch(tokensDir, { persistent: true }, (_eventType, filename) => scheduleBuild(filename));
