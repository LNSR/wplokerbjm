document.addEventListener("DOMContentLoaded", function () {
  if (window.Swiper) {
    const swiper = new Swiper('.mySwiper', {
      loop: false,
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
          slidesPerView: 2,
          spaceBetween: 24,
        },
        1024: {
          slidesPerView: 4,
          spaceBetween: 32,
        },
      },
    });
    document.querySelector('.mySwiper').classList.remove('invisible');
  }
});