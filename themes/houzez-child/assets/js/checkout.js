/* Fresh checkout — stitch interactivity. Não reutiliza código antigo. */
(function(){
  function ready(fn){ if(document.readyState!=='loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  ready(function(){
    var pfBtn = document.getElementById('ipc-tab-pf');
    var pjBtn = document.getElementById('ipc-tab-pj');
    var sel = document.getElementById('billing_persontype');
    function sync(v){
      var isPJ = v==='2';
      if(pfBtn) {
        pfBtn.classList.toggle('bg-white', !isPJ);
        pfBtn.classList.toggle('shadow-sm', !isPJ);
        pfBtn.classList.toggle('font-bold', !isPJ);
        pfBtn.classList.toggle('text-slate-900', !isPJ);
      }
      if(pjBtn){
        pjBtn.classList.toggle('bg-white', isPJ);
        pjBtn.classList.toggle('shadow-sm', isPJ);
        pjBtn.classList.toggle('font-bold', isPJ);
        pjBtn.classList.toggle('text-slate-900', isPJ);
      }
    }
    if(sel){
      sel.addEventListener('change', function(){ sync(sel.value); });
      sync(sel.value);
      if(pfBtn) pfBtn.addEventListener('click', function(){ sel.value='1'; sel.dispatchEvent(new Event('change',{bubbles:true})); });
      if(pjBtn) pjBtn.addEventListener('click', function(){ sel.value='2'; sel.dispatchEvent(new Event('change',{bubbles:true})); });
    }
    // Coupon toggle (usa .showcoupon nativo mas também expande container)
    var couponToggle = document.querySelector('.showcoupon');
    var couponForm = document.querySelector('form.checkout_coupon');
    if(couponToggle && couponForm){
      couponToggle.addEventListener('click', function(e){
        e.preventDefault();
        couponForm.classList.toggle('hidden');
        couponForm.classList.toggle('!block');
      });
    }
  });
})();
