import {spawn,spawnSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {mkdtempSync,writeFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
const temp=mkdtempSync(path.join(tmpdir(),'rasta-browser-'));
const php=process.env.PHP_BINARY||'php';
const secret=randomBytes(32).toString('hex');
const hashed=spawnSync(php,['-r','echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_BCRYPT);'],{input:secret,encoding:'utf8'});
if(hashed.status!==0)throw Error('PHP hashing unavailable');
writeFileSync(path.join(temp,'database.sqlite'),'');
const env={...process.env,APP_ENV:'local',APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:path.join(temp,'database.sqlite'),SESSION_DRIVER:'cookie',CACHE_STORE:'array',UJA_PASSWORD_HASH:hashed.stdout.trim()};
const migrate=spawnSync(php,['artisan','migrate','--force'],{env,stdio:'pipe'});
if(migrate.status!==0){process.stderr.write(migrate.stdout);throw Error('Isolated migration failed');}
const server=spawn(php,['-S','127.0.0.1:8010','-t','public'],{env,stdio:'ignore'});
try {
 let ready=false;for(let i=0;i<50;i++){try{const r=await fetch('http://127.0.0.1:8010/login');if(r.ok){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}
 if(!ready)throw Error('Browser server failed');
 const child=spawn(process.execPath,['node_modules/@playwright/test/cli.js','test'],{env:{...process.env,RASTA_TEST_PASSWORD:secret},stdio:'inherit'});
 const code=await new Promise((resolve,reject)=>{child.on('error',reject);child.on('exit',resolve);});process.exitCode=code??1;
} finally {server.kill('SIGTERM');rmSync(temp,{recursive:true,force:true});}
