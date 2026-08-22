/**
 * FAQ AI Search
 *
 * Injects a search box above the existing FAQ list on /faqs/ and answers
 * visitor questions via /wp-json/fbl/v1/faq-search (grounded in the site's
 * own FAQ/rates/accomodation/catch-and-release content - see
 * fbl-faq-search-system.php).
 *
 * Deliberately injected via JS rather than added to the page's own Divi
 * block content, to steer clear of that content's JSON-escaped storage
 * format entirely - see the deploy notes for why editing that directly is
 * risky. The existing #faqContainer / faq-system.js list is untouched.
 */

(function () {
    'use strict';

    function init() {
        var container = document.getElementById('faqContainer');
        if (!container || !container.parentNode) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'fbl-faq-search';
        wrap.innerHTML =
            '<h2 class="fbl-faq-search__title">Ask a Question</h2>' +
            '<p class="fbl-faq-search__hint">Type your question below and we’ll try to answer it from our FAQs, rates, and policies.</p>' +
            '<form class="fbl-faq-search__form">' +
                '<input type="text" class="fbl-faq-search__input" placeholder="e.g. How much is the deposit?" maxlength="300" required>' +
                '<button type="submit" class="fbl-faq-search__submit">Ask</button>' +
            '</form>' +
            '<div class="fbl-faq-search__result" aria-live="polite"></div>';

        container.parentNode.insertBefore(wrap, container);

        var form   = wrap.querySelector('.fbl-faq-search__form');
        var input  = wrap.querySelector('.fbl-faq-search__input');
        var button = wrap.querySelector('.fbl-faq-search__submit');
        var result = wrap.querySelector('.fbl-faq-search__result');

        var FALLBACK_MESSAGE = 'Please browse the FAQs below or <a href="/contact-us/">contact us</a>.';

        function showResult(html, isError) {
            result.innerHTML = html;
            result.classList.add('is-visible');
            result.classList.toggle('is-error', !!isError);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var question = input.value.trim();
            if (!question) {
                return;
            }

            button.disabled = true;
            input.disabled = true;
            showResult('<span class="fbl-faq-search__spinner"></span>Looking that up…', false);

            fetch('/wp-json/fbl/v1/faq-search', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: question })
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed: ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data && data.success && data.answer) {
                        showResult(data.answer, false);
                    } else if (data && data.answer) {
                        // Server-side fallback response (rate limited / API down).
                        showResult(data.answer, true);
                    } else {
                        showResult(FALLBACK_MESSAGE, true);
                    }
                })
                .catch(function () {
                    showResult(FALLBACK_MESSAGE, true);
                })
                .finally(function () {
                    button.disabled = false;
                    input.disabled = false;
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
