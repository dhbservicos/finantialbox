/**
 * First Financial Box — bills.js
 * Parser de boleto FEBRABAN + CRUD de bills via WP AJAX
 */
var BANCOS_BR={'001':'Banco do Brasil','033':'Santander','077':'Banco Inter','104':'Caixa Econômica Federal','237':'Bradesco','260':'Nubank','341':'Itaú','748':'Sicredi','756':'Sicoob'};
var DATA_BASE_FEBRABAN=new Date(1997,9,7);
var _dadosBoleto=null;

function parsearBoleto(raw){
    var digits=raw.replace(/\D/g,'');
    if(digits.length<44)return{erro:'Código muito curto. Mínimo 44 dígitos.'};
    var codBarras='';
    if(digits.length>=47&&digits.length<=48){codBarras=linhaDigitavelParaCodBarras(digits);if(!codBarras)return{erro:'Linha digitável inválida.'};}
    else if(digits.length===44){codBarras=digits;}
    else{codBarras=digits.length>=47?linhaDigitavelParaCodBarras(digits.substring(0,48))||digits.substring(0,44):digits.substring(0,44);}
    if(codBarras.length<44)return{erro:'Não foi possível extrair código de 44 dígitos.'};
    codBarras=codBarras.substring(0,44);
    var resultado={codigo:codBarras,valor:null,vencimento:null,banco:null,nomeBanco:null,tipo:null,aviso:null};
    if(codBarras[0]!=='8'){
        resultado.tipo='Boleto Bancário';resultado.banco=codBarras.substring(0,3);resultado.nomeBanco=BANCOS_BR[resultado.banco]||'Banco '+resultado.banco;
        var fator=parseInt(codBarras.substring(5,9),10);
        if(fator>0){var dv=new Date(DATA_BASE_FEBRABAN.getTime());dv.setDate(dv.getDate()+fator);resultado.vencimento=dv.toISOString().slice(0,10);}
        else resultado.aviso='Boleto sem data de vencimento (à vista).';
        var val=parseInt(codBarras.substring(9,19),10)/100;
        if(val>0)resultado.valor=val;else resultado.aviso=(resultado.aviso?resultado.aviso+' ':'')+'Valor não informado.';
    }else{
        resultado.tipo='Arrecadação/Convênio';resultado.banco='000';resultado.nomeBanco='Arrecadação (DARF/FGTS/Concessionária)';
        var idVal=codBarras[2];if(idVal==='6'||idVal==='7')resultado.valor=parseInt(codBarras.substring(4,15),10)/100;
        resultado.aviso='Data de vencimento não disponível neste tipo.';
    }
    return resultado;
}
function linhaDigitavelParaCodBarras(digits){if(digits.length<47)return'';try{var c1=digits.substring(0,9);var c2=digits.substring(10,20);var c3=digits.substring(21,31);var c4=digits.substring(32,33);var c5=digits.substring(33,48);var cb=c1.substring(0,3)+c1.substring(3,4)+c4+c5.substring(0,4)+c5.substring(4,14)+c1.substring(4,9)+c2+c3;return cb.length===44?cb:'';}catch(e){return'';}}

function analisarBoleto(){
    var input=document.getElementById('boletoInput').value.trim();
    document.getElementById('boletoErro').style.display='none';document.getElementById('boletoResultado').style.display='none';document.getElementById('btnUsarDados').style.display='none';_dadosBoleto=null;
    if(!input){document.getElementById('boletoErro').textContent='Cole o código de barras.';document.getElementById('boletoErro').style.display='';return;}
    var r=parsearBoleto(input);
    if(r.erro){document.getElementById('boletoErro').textContent='\u26a0 '+r.erro;document.getElementById('boletoErro').style.display='';return;}
    _dadosBoleto=r;
    document.getElementById('boletoValor').value=r.valor?r.valor.toFixed(2):'';
    document.getElementById('boletoVencimento').value=r.vencimento||'';
    document.getElementById('boletoBanco').value=(r.nomeBanco||'')+(r.tipo?' — '+r.tipo:'');
    document.getElementById('boletoResultado').style.display='';document.getElementById('btnUsarDados').style.display='';
}

function usarDadosBoleto(){
    if(!_dadosBoleto)return;
    var bm=bootstrap.Modal.getInstance(document.getElementById('boletoModal'));if(bm)bm.hide();
    if(_dadosBoleto.valor)document.getElementById('billAmount').value=_dadosBoleto.valor.toFixed(2);
    if(_dadosBoleto.vencimento)document.getElementById('billDue').value=_dadosBoleto.vencimento;
    if(_dadosBoleto.nomeBanco)document.getElementById('billNotes').value=_dadosBoleto.nomeBanco;
    if(!document.getElementById('billDesc').value&&_dadosBoleto.nomeBanco)document.getElementById('billDesc').value='Boleto '+_dadosBoleto.nomeBanco;
    new bootstrap.Modal(document.getElementById('billModal')).show();
}

document.addEventListener('DOMContentLoaded',function(){
    var bm=document.getElementById('boletoModal');
    if(bm)bm.addEventListener('hidden.bs.modal',function(){
        document.getElementById('boletoInput').value='';document.getElementById('boletoErro').style.display='none';
        document.getElementById('boletoResultado').style.display='none';document.getElementById('btnUsarDados').style.display='none';_dadosBoleto=null;
    });
});
