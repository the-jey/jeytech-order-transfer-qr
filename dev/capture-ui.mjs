import { chromium } from 'playwright';
import { readFile, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const browser = await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});
const reports=process.argv[2]==='fr'?JSON.parse(await readFile('dev/ui-verification.json')):[];
for (const [locale,port,offset] of [['en',9406,0],['fr',9407,3]]) {
 if(process.argv[2] && process.argv[2]!==locale)continue;
 const base=`http://127.0.0.1:${port}`; const fixture=JSON.parse(await readFile(`dev/.demo-${locale}.json`));
 const ctx=await browser.newContext({viewport:{width:1440,height:1100},deviceScaleFactor:1});
 const page=await ctx.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill('admin');await page.locator('#user_pass').fill('password');
 await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
 await page.goto(base+'/wp-admin/admin.php?page=jeytech-otqr');await page.locator('.jeytech-otqr').waitFor();
 const settings=page.locator('.jeytech-otqr');
 assert.equal(await settings.evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(14, 15, 17)');
 assert.match(await settings.innerText(),locale==='fr'?/bancaire/i : /Bank account/);
 await settings.screenshot({path:`.wordpress-org/screenshot-${offset+1}.png`});
 await page.screenshot({path:`dev/outputs/settings-${locale}-desktop.png`,fullPage:true});
 const ref=page.locator('input[name="jeytech_otqr_settings[reference]"]');
 const save=page.locator('#submit');
 await ref.fill('WEB-{order_number}');await Promise.all([page.waitForNavigation(),save.click()]);
 assert.equal(await ref.inputValue(),'WEB-{order_number}');
 let image=await ctx.request.get(fixture.image);assert.equal(image.status(),403);
 await ref.fill('JT-{order_number}');await Promise.all([page.waitForNavigation(),save.click()]);
 image=await ctx.request.get(fixture.image);assert.equal(image.status(),200);assert.match(image.headers()['content-type'],/image\/png/);assert.match(image.headers()['cache-control'],/private, no-store/);
 await writeFile(`dev/outputs/http-image-${locale}.png`,await image.body());
 for(const [field,value]of [['signature','a'.repeat(64)],['order_id','999999'],['expires','1']]){const url=new URL(fixture.image);url.searchParams.set(field,value);assert.equal((await ctx.request.get(url.href)).status(),403);}
 const enabled=page.locator('input[name="jeytech_otqr_settings[enabled]"]');await enabled.uncheck();await Promise.all([page.waitForNavigation(),save.click()]);assert.equal((await ctx.request.get(fixture.image)).status(),403);
 await enabled.check();await Promise.all([page.waitForNavigation(),save.click()]);assert.equal((await ctx.request.get(fixture.image)).status(),200);
 await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await settings.screenshot({path:`dev/outputs/settings-${locale}-mobile.png`});
 await page.setViewportSize({width:1440,height:1100});
 for(const [label,url]of [['blocks',fixture.confirmation],['classic',fixture.classicConfirmation]]){
  await page.goto(url);
  if(await page.locator('#verify-email').count()){await page.locator('#verify-email').fill('customer@example.test');await Promise.all([page.waitForNavigation(),page.locator('#verify-email-submit').click()]);}
  const card=page.locator('.jeytech-otqr-payment');assert.equal(await card.count(),1);await card.waitFor();
  assert.match(await card.innerText(),locale==='fr'?/Payer par virement/:/Pay by bank transfer/);
  assert.match(await card.innerText(),/EUR 19.90/);assert.match(await card.innerText(),/JT-11/);
  assert(await card.locator('img').evaluate(e=>e.complete&&e.naturalWidth>0));
  if(label==='blocks')await card.screenshot({path:`.wordpress-org/screenshot-${offset+2}.png`});
  await page.screenshot({path:`dev/outputs/order-${label}-${locale}-desktop.png`,fullPage:true});
  await page.setViewportSize({width:390,height:844});await page.reload();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await card.screenshot({path:`dev/outputs/order-${label}-${locale}-mobile.png`});await page.setViewportSize({width:1440,height:1100});
 }
 await page.goto(base+`/wp-content/otqr-dev/preview-email-${locale}.html`);const emailCard=page.locator('.jeytech-otqr-payment');await emailCard.waitFor();assert(await emailCard.locator('img').evaluate(e=>e.complete&&e.naturalWidth>0));
 await emailCard.screenshot({path:`.wordpress-org/screenshot-${offset+3}.png`});await page.screenshot({path:`dev/outputs/email-${locale}-desktop.png`,fullPage:true});
 await page.setViewportSize({width:390,height:844});await page.screenshot({path:`dev/outputs/email-${locale}-mobile.png`,fullPage:true});
 reports.push({locale,versions:fixture.versions,settingsSave:true,settingsRevoke:true,disabledRevoke:true,httpPng:true,tamperedRequestsDenied:3,confirmations:['blocks','classic'],viewports:[1440,390],email:'native WC HTML, captured without sending',errors});
 assert.deepEqual(errors,[]);
 await writeFile('dev/ui-verification.json',JSON.stringify(reports,null,2)+'\n');
 await ctx.close();
}
await browser.close();await writeFile('dev/ui-verification.json',JSON.stringify(reports,null,2)+'\n');console.log(JSON.stringify(reports,null,2));
