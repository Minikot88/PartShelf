const {defineConfig}=require('./node_modules/@playwright/test');
const {resolve}=require('node:path');
process.env.PLAYWRIGHT_BROWSERS_PATH=resolve('node_modules/.cache/ms-playwright');
module.exports=defineConfig({testDir:'./e2e/restore',grepInvert:/synthetic UI/,workers:1,retries:0,timeout:45000,reporter:[['list'],['json',{outputFile:'../.local/restore-verification/playwright-results.json'}]],use:{baseURL:'http://127.0.0.1:5177',storageState:'../.local/restore-verification/auth/owner.json',headless:true,serviceWorkers:'block',trace:'off',video:'off',screenshot:'off'}});

