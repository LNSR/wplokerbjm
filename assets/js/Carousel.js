document.addEventListener("DOMContentLoaded", function () {
  if (window.Glider) {
    const gliderElem = document.querySelector(".glider-contain");
    const glider = new Glider(document.querySelector(".glider"), {
      slidesToShow: 1,
      slidesToScroll: 1,
      dots: ".glider-dots",
      arrows: {
        prev: ".glider-prev",
        next: ".glider-next",
      },
      draggable: true,
      scrollLock: true,
      animationDuration: 800,
      animationTimingFunc: "ease-in-out",
      responsive: [
        {
          breakpoint: 0,
          settings: {
            slidesToShow: 1.1,
            slidesToScroll: 1,
            draggable: true,
            scrollLock: true,
            peek: { before: 0, after: 60 },
          },
        },
        {
          // Tablet: 2 slides, peek next
          breakpoint: 640,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 1,
            draggable: true,
            scrollLock: true,
            peek: { before: 0, after: 60 },
          },
        },
        {
          // Desktop: 4 slides, no peek
          breakpoint: 1024,
          settings: {
            slidesToShow: 4,
            slidesToScroll: 1,
            draggable: true,
            scrollLock: true,
            peek: 0,
          },
        },
      ],
    });

    if (gliderElem) {
      gliderElem.classList.remove("carousel-hidden");
      gliderElem.classList.add("carousel-ready");
    }

    let autoScroll = setInterval(() => {
      glider.scrollItem("next");
    }, 4000);

    // Pause on mouse enter, resume on mouse leave
    gliderElem.addEventListener("mouseenter", () => clearInterval(autoScroll));
    gliderElem.addEventListener("mouseleave", () => {
      autoScroll = setInterval(() => {
        glider.scrollItem("next");
      }, 4000);
    });
  }
});
