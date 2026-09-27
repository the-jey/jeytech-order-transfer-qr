import {chromium} from 'playwright';
import fs from 'node:fs/promises';import assert from 'node:assert/strict';
const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});const reports=[];
for(const kind of ['classic','blocks']){
 const c=await b.newContext({viewport:{width:1440,height:1100}});const p=await c.newPage();const f=JSON.parse(await fs.readFile('dev/.demo-en.json'));
 await p.goto('http://127.0.0.1:9406/?add-to-cart='+f.productId);await p.goto(kind==='classic'?f.classicCheckout:f.checkout);
 const ids=kind==='classic'?{first:'#billing_first_name',last:'#billing_last_name',address:'#billing_address_1',postcode:'#billing_postcode',city:'#billing_city',phone:'#billing_phone',email:'#billing_email'}:{first:'#billing-first_name',last:'#billing-last_name',address:'#billing-address_1',postcode:'#billing-postcode',city:'#billing-city',phone:'#billing-phone',email:'#email'};
 for(const [key,value]of Object.entries({first:'Camille',last:'Demo',address:'12 rue de la Démo',postcode:'75001',city:'Paris',phone:'0612345678',email:'customer@example.test'}))await p.locator(ids[key]).fill(value);
 if(kind==='classic'){await p.locator('#payment_method_bacs').check();await p.locator('#billing_email').blur();await p.waitForTimeout(1000);await p.locator('#place_order').click();}
 else{await p.locator('#radio-control-wc-payment-method-options-bacs').check();await p.getByRole('button',{name:'Place Order',exact:true}).click();}
 try{await p.waitForURL('**/order-received/**',{timeout:20000});}catch(e){await p.screenshot({path:`dev/outputs/checkout-${kind}-failure.png`,fullPage:true});console.log(kind,p.url(),(await p.locator('body').innerText()).slice(-4500));throw e;}const card=p.locator('.jeytech-otqr-payment');await card.waitFor();assert.equal(await card.count(),1);assert.match(await card.innerText(),/EUR 19.90/);assert(await card.locator('img').evaluate(e=>e.complete&&e.naturalWidth>0));
 const imageResponse=await c.request.get(await card.locator('img').getAttribute('src'));assert.equal(imageResponse.status(),200);
 await fs.writeFile(`dev/outputs/checkout-${kind}-qr.png`,await imageResponse.body());await p.screenshot({path:`dev/outputs/checkout-${kind}-completed.png`,fullPage:true});
 reports.push({kind,orderId:Number(new URL(p.url()).pathname.split('/').filter(Boolean).at(-1)),amount:'19.90',imageStatus:200,realCheckoutSubmitted:true,mail:'Local demo MU intercepts all mail. No payment processed.'});await c.close();
}
await b.close();await fs.writeFile('dev/checkout-verification.json',JSON.stringify(reports,null,2)+'\n');console.log(JSON.stringify(reports));
