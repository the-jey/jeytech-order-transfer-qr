import {chromium} from 'playwright';
const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});const p=await b.newPage({viewport:{width:1800,height:1100}});await p.goto('file://'+process.cwd()+'/dev/assets-src/branding.html');await p.evaluate(()=>document.fonts.ready);
await p.locator('#banner').screenshot({path:'.wordpress-org/banner-1544x500.png'});await p.locator('#icon').screenshot({path:'.wordpress-org/icon-256x256.png'});await p.locator('#product').screenshot({path:'dev/assets-src/product-otqr.png'});await b.close();
