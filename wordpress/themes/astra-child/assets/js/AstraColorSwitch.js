document.addEventListener("DOMContentLoaded", function () {
  const btn = document.getElementById("astra-color-switch");
  const wrapper = document.getElementById("astra-color-switch-wrapper");
  const html = document.documentElement;

  if (!btn || !wrapper) return;

  const currentTheme = html.getAttribute("data-theme");
  btn.checked = currentTheme === "dark";

  // Add listener for theme changes via the button
  btn.addEventListener("change", function () {
    if (btn.checked) {
      html.setAttribute("data-theme", "dark");
      localStorage.setItem("astra-theme", "dark");
    } else {
      html.setAttribute("data-theme", "light");
      localStorage.setItem("astra-theme", "light");
    }
  });

  wrapper.style.opacity = "0";
  let hideTimeout;

  function showButton() {
    wrapper.style.opacity = "1";
    clearTimeout(hideTimeout);
    hideTimeout = setTimeout(() => {
      wrapper.style.opacity = "0";
    }, 1500);
  }

  window.addEventListener("mousemove", showButton, { passive: true });
  window.addEventListener("scroll", showButton, { passive: true });
});
