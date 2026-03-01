document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fbl-slider').forEach(function (slider) {
      var slides = slider.querySelectorAll('.fbl-slide');
      var index = 0;
      var delay = parseInt(slider.getAttribute('data-fbl-autoplay') || '5000', 10);
  
      function showSlide(i) {
        slides.forEach(function (s, idx) {
          s.classList.toggle('is-active', idx === i);
        });
      }
  
      function nextSlide() {
        index = (index + 1) % slides.length;
        showSlide(index);
      }
  
      // Start slider
      showSlide(0);
      var timer = setInterval(nextSlide, delay);
  
      // Prev button
      var prevBtn = slider.querySelector('.fbl-prev');
      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          clearInterval(timer);
          index = (index - 1 + slides.length) % slides.length;
          showSlide(index);
        });
      }
  
      // Next button
      var nextBtn = slider.querySelector('.fbl-next');
      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          clearInterval(timer);
          nextSlide();
        });
      }
    });
  });
  