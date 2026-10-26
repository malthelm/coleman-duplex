const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
const code = fs.readFileSync('modules/colemanstay/views/js/requests.js','utf8');
for (const [name, value, download] of [['decision','approved',false],['operation','lease_save',false],['operation','profile_submit',false],['operation','login',false],['operation','file_download',true],['operation','receipt',true],['operation','csv',true]]) {
  const inputs=[],attributes={},button={name,value,disabled:false,textContent:'Submit'};
  const form={dataset:{},getAttribute:k=>attributes[k],setAttribute:(k,v)=>attributes[k]=v,appendChild:i=>inputs.push(i),querySelectorAll:()=>[button],addEventListener:(name,cb)=>form.submit=cb};
  vm.runInNewContext(code,{document:{querySelectorAll:()=>[form],querySelector:()=>null,createElement:()=>({})},window:{addEventListener:()=>{}}});
  let prevented=false; form.submit({submitter:button,preventDefault:()=>prevented=true});
  assert.equal(prevented,false); assert.equal(button.disabled,!download); assert.equal(button.textContent,'Submit');
  if(download){assert.equal(inputs.length,0);assert.equal(attributes['aria-busy'],undefined);}
  else{assert.equal(inputs[0].name,name);assert.equal(inputs[0].value,value);form.submit({submitter:button,preventDefault:()=>prevented=true});assert.equal(prevented,true);}
  console.log('PASS browser handler:',value);
}
