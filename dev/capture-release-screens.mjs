import {chromium} from 'playwright';import fs from 'node:fs/promises';
const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});
for(const [locale,port,offset]of [['en',9406,0],['fr',9407,3]]){
 const ctx=await b.newContext({viewport:{width:1440,height:1100},deviceScaleFactor:1});const p=await ctx.newPage();const base=`http://127.0.0.1:${port}`;const f=JSON.parse(await fs.readFile(`dev/.demo-${locale}.json`));
 await p.goto(base+'/wp-login.php');await p.locator('#user_login').fill('admin');await p.locator('#user_pass').fill('password');await Promise.all([p.waitForURL('**/wp-admin/**'),p.locator('#wp-submit').click()]);
 await p.goto(base+'/wp-admin/admin.php?page=jeytech-otqr');await p.locator('.jeytech-otqr').screenshot({path:`.wordpress-org/screenshot-${offset+1}.png`});
 await p.goto(f.confirmation);if(await p.locator('#verify-email').count()){await p.locator('#verify-email').fill('customer@example.test');await Promise.all([p.waitForNavigation(),p.locator('#verify-email-submit').click()]);}
 await p.waitForFunction(()=>{const image=document.querySelector('.jeytech-otqr-payment img');return image?.complete&&image.naturalWidth>0;});
 await p.locator('.jeytech-otqr-payment').screenshot({path:`.wordpress-org/screenshot-${offset+2}.png`});
 await p.goto(base+`/wp-content/otqr-dev/preview-email-${locale}.html`);await p.waitForFunction(()=>{const image=document.querySelector('.jeytech-otqr-payment img');return image?.complete&&image.naturalWidth>0;});
 await p.locator('.jeytech-otqr-payment').screenshot({path:`.wordpress-org/screenshot-${offset+3}.png`});await ctx.close();
}
await b.close();console.log('Six final screenshots from production files in dist.');
