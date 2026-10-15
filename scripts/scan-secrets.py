"""Scan exactly the files proposed for the commit/archive; never print secret values."""
import pathlib,re,subprocess,sys
root=pathlib.Path(__file__).resolve().parents[1]
files=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=root).decode().split('\0')
failed=[]
patterns=[r'-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----',r'gh[pousr]_[A-Za-z0-9]{30,}',r'(?:sk_live_|AKIA)[A-Za-z0-9]{16,}',r'(?m)^UJA_PASSWORD_HASH=[^\s\n]+',r'(?m)^APP_KEY=base64:.+']
for name in set(files):
 if not name: continue
 p=root/name
 if p.name=='.env' or (p.name.startswith('.env.') and p.name!='.env.example') or any(x in p.parts for x in ['vendor','node_modules']) or p.suffix in ['.sqlite','.zip']: failed.append(name);continue
 if not p.is_file():continue
 text=p.read_bytes().decode('utf-8',errors='ignore')
 if any(re.search(pattern,text) for pattern in patterns):failed.append(name)
if failed:
 print('FAIL: inspect files:',', '.join(sorted(set(failed))));sys.exit(1)
print('PASS: no environment files, dependency folders, private keys, credential patterns or populated secret bindings in commit candidates.')
