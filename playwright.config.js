import {existsSync} from 'node:fs';
import {defineConfig} from '@playwright/test';
const windowsChrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
const chromium=process.env.CHROMIUM_PATH||(process.platform==='win32'&&existsSync(windowsChrome)?windowsChrome:'/usr/bin/chromium');
export default defineConfig({testDir:'./tests/Browser',timeout:90000,workers:1,use:{baseURL:process.env.RASTA_TEST_BASE_URL||'http://127.0.0.1:8010',headless:true,launchOptions:{executablePath:chromium,args:['--no-sandbox']},screenshot:'off',trace:'off',video:'off'},reporter:'list',outputDir:'storage/browser-results'});
