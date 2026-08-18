/* =========================================================
   FBL RATE ESTIMATOR
   Ported from the Divi Code module that used to live on
   /rez-calendar/. Behaviour is unchanged; the only difference
   is that FBL_RATES/FBL_TAX are no longer hardcoded here -
   they come from fblRateData, localized server-side in
   inc/rate-estimator.php from the fbl_rate_settings option.
   ========================================================= */

var FBL_RATES   = (typeof fblRateData !== 'undefined') ? fblRateData.rates       : {};
var FBL_TAX     = (typeof fblRateData !== 'undefined') ? fblRateData.taxRate     : 0.13;
var FBL_DEPOSIT = (typeof fblRateData !== 'undefined') ? fblRateData.depositRate : 0.15;

var fblPlan = 'cabin';

function fblSetPlan(plan) {
  fblPlan = plan;
  document.getElementById('fbl-btn-boat').classList.toggle('active', plan === 'cabin');
  document.getElementById('fbl-btn-outpost').classList.toggle('active', plan === 'outpost');

  const premiumRow = document.getElementById('fbl-premium-row');
  const premiumChk = document.getElementById('fbl-premium');
  const adultsSel  = document.getElementById('fbl-adults');
  const currentAdults = parseInt(adultsSel.value);

  if (plan === 'outpost') {
    adultsSel.innerHTML = '';
    for (let i = 1; i <= 6; i++) {
      const opt = document.createElement('option');
      opt.value = i;
      opt.textContent = i;
      if (i === Math.min(currentAdults, 6)) opt.selected = true;
      adultsSel.appendChild(opt);
    }
    premiumRow.style.display = 'none';
    premiumChk.checked = false;
    document.getElementById('fbl-field-adultboat').classList.add('fbl-hidden');
    document.getElementById('fbl-field-childboat').classList.add('fbl-hidden');
  } else {
    adultsSel.innerHTML = '';
    for (let i = 1; i <= 8; i++) {
      const opt = document.createElement('option');
      opt.value = i;
      opt.textContent = i;
      if (i === currentAdults) opt.selected = true;
      adultsSel.appendChild(opt);
    }
    premiumRow.style.display = 'flex';
    fblSyncBoatFields();
  }

  fblCalc();
}

function fblTogglePremium() {
  fblSyncBoatFields();
  setTimeout(fblCalc, 0);
}

function fblSyncBoatFields() {
  const premium  = document.getElementById('fbl-premium').checked;
  const adults   = parseInt(document.getElementById('fbl-adults').value);
  const children = parseInt(document.getElementById('fbl-children').value);

  const adultBoatField = document.getElementById('fbl-field-adultboat');
  const childBoatField = document.getElementById('fbl-field-childboat');
  const adultBoatSel   = document.getElementById('fbl-adultboat');
  const childBoatSel   = document.getElementById('fbl-childboat');

  if (premium) {
    adultBoatField.classList.remove('fbl-hidden');
    childBoatField.classList.remove('fbl-hidden');
    const syncedAdult = Math.min(adults, 8);
    const syncedChild = Math.min(children, 4);
    adultBoatSel.value = syncedAdult;
    childBoatSel.value = syncedChild;
  } else {
    adultBoatField.classList.add('fbl-hidden');
    childBoatField.classList.add('fbl-hidden');
    adultBoatSel.value = 0;
    childBoatSel.value = 0;
  }
}

function fblFmt(n) {
  return '$' + n.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fblLine(label, amount) {
  if (amount === 0) return '';
  return `<div class="fbl-line">
    <span class="fbl-line-label">${label}</span>
    <span class="fbl-line-amount">${fblFmt(amount)}</span>
  </div>`;
}

// Same .fbl-price/data-fbl-usd shape [fbl_rate] renders server-side
// (inc/rate-estimator.php, including the "(USD)" placement right after the
// source figure and before the ≈-converted .fbl-price-fx figure), so the
// currency converter's generic .fbl-price walk (js/currency-converter.js)
// picks these up with no special-casing - see fblRefreshCurrencyDisplay()
// below.
function fblPriceSpan(usd) {
  return `<span class="fbl-price" data-fbl-usd="${usd.toFixed(2)}">${fblFmt(usd)} (USD) <span class="fbl-price-fx"></span></span>`;
}

var fblLastTotal = 0;

function fblCalc() {
  const r = FBL_RATES[fblPlan];
  if (!r) return;

  const adults  = parseInt(document.getElementById('fbl-adults').value);
  const children= parseInt(document.getElementById('fbl-children').value);
  const premium = document.getElementById('fbl-premium').checked;
  const adultboat = premium ? parseInt(document.getElementById('fbl-adultboat').value) : 0;
  const childboat = premium ? parseInt(document.getElementById('fbl-childboat').value) : 0;
  const pets    = parseInt(document.getElementById('fbl-pet').value);

  document.getElementById('fbl-hint-adults').textContent    = fblFmt(r.base) + '/adult';
  document.getElementById('fbl-hint-children').textContent  = fblFmt(r.child) + '/child';
  document.getElementById('fbl-hint-adultboat').textContent = fblFmt(r.adultBoat) + '/person';
  document.getElementById('fbl-hint-childboat').textContent = fblFmt(r.childBoat) + '/child';
  document.getElementById('fbl-hint-pet').textContent       = fblFmt(r.pet) + '/pet';

  const baseAmt       = r.base;
  const extraAdultAmt = (adults - 1) * r.extraAdult;
  const childAmt      = children * r.child;
  const adultBoatAmt  = adultboat * r.adultBoat;
  const childBoatAmt  = childboat * r.childBoat;
  const petAmt        = pets * r.pet;

  const pretax = baseAmt + extraAdultAmt + childAmt + adultBoatAmt + childBoatAmt + petAmt;
  const tax    = pretax * FBL_TAX;
  const total  = pretax + tax;

  let html = '';
  html += fblLine(`1 adult (base rate)`, baseAmt);
  if (extraAdultAmt > 0) html += fblLine(`${adults - 1} extra adult${adults-1>1?'s':''} × ${fblFmt(r.extraAdult)}`, extraAdultAmt);
  if (childAmt > 0)      html += fblLine(`${children} child${children>1?'ren':''} × ${fblFmt(r.child)}`, childAmt);
  if (adultBoatAmt > 0)  html += fblLine(`${adultboat} adult premium boat × ${fblFmt(r.adultBoat)}`, adultBoatAmt);
  if (childBoatAmt > 0)  html += fblLine(`${childboat} child premium boat × ${fblFmt(r.childBoat)}`, childBoatAmt);
  if (petAmt > 0)        html += fblLine(`${pets} pet${pets>1?'s':''} × ${fblFmt(r.pet)}`, petAmt);

  document.getElementById('fbl-lines').innerHTML = html;
  document.getElementById('fbl-pretax').textContent = fblFmt(pretax);
  document.getElementById('fbl-tax').textContent    = fblFmt(tax);
  document.getElementById('fbl-total').textContent  = fblFmt(total);

  fblLastTotal = total;

  // Deposit is calculated on the POST-TAX total (15% of `total`, not
  // `pretax`). The client's copy ("A deposit of 15% of the total
  // price...") doesn't say pre-tax or post-tax explicitly, so this is
  // a reasonable-reading assumption, not a confirmed business rule -
  // flip to `pretax * FBL_DEPOSIT` here (and adjust `balance` to match)
  // if the client says otherwise.
  const deposit = total * FBL_DEPOSIT;
  const balance = total - deposit;

  document.getElementById('fbl-deposit').innerHTML = fblPriceSpan(deposit);
  document.getElementById('fbl-balance').innerHTML = fblPriceSpan(balance);

  // Currency converter (js/currency-converter.js) hooks in here if present -
  // one call refreshes both the dedicated #fbl-fx-row grand-total display
  // and every .fbl-price figure above, deposit/balance included.
  if (typeof fblRefreshCurrencyDisplay === 'function') {
    fblRefreshCurrencyDisplay();
  }
}

document.getElementById('fbl-adults').addEventListener('change', () => { fblSyncBoatFields(); fblCalc(); });
document.getElementById('fbl-children').addEventListener('change', () => { fblSyncBoatFields(); fblCalc(); });

// Init
document.getElementById('fbl-field-adultboat').classList.add('fbl-hidden');
document.getElementById('fbl-field-childboat').classList.add('fbl-hidden');
fblCalc();
