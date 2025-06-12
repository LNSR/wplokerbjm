document.addEventListener("DOMContentLoaded", function () {
  if (window.Swiper) {
    const swiperElement = document.querySelector('.mySwiper');
    const slideCount = swiperElement ? swiperElement.querySelectorAll('.swiper-slide').length : 0;
    
    const hasEnoughSlides = slideCount >= 8;
    
    const swiper = new Swiper('.mySwiper', {
      loop: hasEnoughSlides,
      slidesPerView: 1.3,
      spaceBetween: 16,
      autoplay: {
        delay: 5000,
        disableOnInteraction: false,
      },
      scrollbar: {
        el: '.swiper-scrollbar',
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      breakpoints: {
        640: {
          slidesPerView: Math.min(2, slideCount),
          spaceBetween: 24,
        },
        1024: {
          slidesPerView: Math.min(4, slideCount),
          spaceBetween: 32,
        },
      },
    });
    document.querySelector('.mySwiper').classList.remove('invisible');
  }
});