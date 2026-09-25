const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let onReady, onKey, changed = 0, focused = 0;
const campo = {value:'', addEventListener: (_,fn) => { onKey=fn; }};
const option = {value:'7', text:'Produto de teste', getAttribute: key => ({'data-code':'7891234567890','data-sku':'SC-0007'}[key] || '')};
const select = {value:'', options:[option], dispatchEvent: () => changed++};
const feedback = {textContent:''};
const document = {
    addEventListener: (event,fn) => { if(event==='DOMContentLoaded') onReady=fn; },
    querySelector: selector => ({'[data-product-code]':campo,'[data-product-select]':select,'[data-product-code-feedback]':feedback}[selector] || null),
    querySelectorAll: () => [],
    getElementById: id => id==='quantidade' ? {focus: () => focused++} : null,
};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/js/script.js','utf8'), {document, window:{addEventListener(){}}, Event:class Event {}});
onReady();
for(const code of ['7891234567890','sc-0007']) {
    campo.value=code;
    select.value='';
    onKey({key:'Enter',preventDefault(){}});
    assert.equal(select.value,'7');
    assert.equal(campo.value,'');
}
campo.value='desconhecido';
onKey({key:'Enter',preventDefault(){}});
assert.equal(changed,2);
assert.equal(focused,2);
assert.match(feedback.textContent,/Nenhum produto/);
console.log('3 cenários aprovados: código de barras, SKU e código inexistente.');
