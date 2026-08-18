/* =========================================================
   FBL CURRENCY CONVERTER
   Reads the fx rate table localized by inc/currency-converter.php
   (fblFxData). All conversion happens here, client-side, from
   that cached table - no per-visitor API calls.

   - If fblFxData.available is false (no rate has ever been
     fetched), the whole converter stays hidden: no selector is
     built and no .fbl-price element is touched.
   - Choice persists in localStorage under 'fbl_currency' so it
     carries across /rates/ and /rez-calendar/.
   - USD stays primary everywhere; converted figures are always
     secondary/approximate, with the rate's fetch date shown.
   ========================================================= */

(function () {
  if (typeof fblFxData === 'undefined' || !fblFxData.available) return;

  var RATES = fblFxData.rates;
  var DATE  = fblFxData.date;
  var CODES = Object.keys(RATES);
  if (!CODES.length) return;

  var STORAGE_KEY = 'fbl_currency';

  function fmt(amount, code) {
    // Let Intl handle per-currency decimal rules (JPY = 0, most
    // others = 2) instead of hardcoding a decimals table.
    try {
      return new Intl.NumberFormat('en-US', { style: 'currency', currency: code }).format(amount);
    } catch (e) {
      return code + ' ' + amount.toFixed(2);
    }
  }

  function getCurrency() {
    var saved = localStorage.getItem(STORAGE_KEY);
    return (saved === 'USD' || CODES.indexOf(saved) !== -1) ? saved : 'USD';
  }

  function setCurrency(code) {
    localStorage.setItem(STORAGE_KEY, code);
  }

  /* ---------------------------------------------------------
     Selector - one shared control per page, fixed corner widget
     so it works the same regardless of where it lands in the
     Divi layout (estimator page vs. rates page prose).
     --------------------------------------------------------- */
  function buildSelector() {
    var wrap = document.createElement('div');
    wrap.className = 'fbl-currency-picker';

    var label = document.createElement('label');
    label.textContent = 'Show prices in ';
    label.setAttribute('for', 'fbl-currency-select');

    var select = document.createElement('select');
    select.id = 'fbl-currency-select';

    var usdOpt = document.createElement('option');
    usdOpt.value = 'USD';
    usdOpt.textContent = 'USD';
    select.appendChild(usdOpt);

    CODES.forEach(function (code) {
      var opt = document.createElement('option');
      opt.value = code;
      opt.textContent = code;
      select.appendChild(opt);
    });

    select.value = getCurrency();

    select.addEventListener('change', function () {
      setCurrency(select.value);
      updateAll();
    });

    label.appendChild(select);
    wrap.appendChild(label);
    document.body.appendChild(wrap);

    return select;
  }

  /* ---------------------------------------------------------
     [fbl_rate] figures - each has data-fbl-usd and an empty
     .fbl-price-fx span to fill in.
     --------------------------------------------------------- */
  function updatePriceFigures(code) {
    document.querySelectorAll('.fbl-price').forEach(function (el) {
      var usd = parseFloat(el.getAttribute('data-fbl-usd'));
      var fx  = el.querySelector('.fbl-price-fx');
      if (!fx || isNaN(usd)) return;

      if (code === 'USD') {
        fx.textContent = '';
        return;
      }

      var converted = usd * RATES[code];
      fx.textContent = '≈ ' + fmt(converted, code);
      fx.title = 'Approximate. Billing is in USD (' + fmt(usd, 'USD') + '). Rate as of ' + DATE + '. Your bank’s exchange rate and any conversion fees will differ.';
    });
  }

  /* ---------------------------------------------------------
     Rate estimator total - called from js/rate-estimator.js
     after every fblCalc(). container is #fbl-fx-row.
     --------------------------------------------------------- */
  window.fblUpdateFxDisplay = function (usdTotal, container) {
    if (!container) return;
    var code = getCurrency();

    if (code === 'USD') {
      container.hidden = true;
      container.innerHTML = '';
      return;
    }

    var converted = usdTotal * RATES[code];
    container.hidden = false;
    container.innerHTML =
      '<div class="fbl-fx-amount">≈ ' + fmt(converted, code) + '</div>' +
      '<div class="fbl-fx-note">Billing is in USD. Your bank’s exchange rate and any conversion fees will differ. Rate as of ' + DATE + '.</div>';
  };

  function updateAll() {
    var code = getCurrency();
    updatePriceFigures(code);

    var fxRow = document.getElementById('fbl-fx-row');
    if (fxRow && typeof fblLastTotal !== 'undefined') {
      window.fblUpdateFxDisplay(fblLastTotal, fxRow);
    }
  }

  // Shared refresh entry point for js/rate-estimator.js: walks every
  // .fbl-price figure generically (deposit/balance lines included, no
  // special-casing needed there) and refreshes the dedicated #fbl-fx-row
  // grand-total display, all from the current currency selection.
  window.fblRefreshCurrencyDisplay = updateAll;

  document.addEventListener('DOMContentLoaded', function () {
    buildSelector();
    updateAll();
  });
})();
