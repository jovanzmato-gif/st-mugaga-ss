// Turns a slider element into an auto-advancing slideshow
function initSlider(root) {
  const slides = root.querySelectorAll('.slide');
  const dotsBox = root.querySelector('.slider-dots');
  const INTERVAL = 5000;   // time each slide stays on screen, in milliseconds (5000 = 5 seconds)
  let current = 0;
  let timer = null;

  // Create one dot button for each slide
  slides.forEach((_, i) => {
    const dot = document.createElement('button');
    dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
    dot.addEventListener('click', () => {
      show(i);
      start();   // restart the timer so the slide gets its full time
    });
    dotsBox.appendChild(dot);
  });

  // Show the slide at position "index", and mark its dot as active
  function show(index) {
    // The modulo (%) wraps around: after the last slide comes the first
    current = (index + slides.length) % slides.length;

    slides.forEach((slide, i) => slide.classList.toggle('active', i === current));
    [...dotsBox.children].forEach((dot, i) => dot.classList.toggle('active', i === current));
  }

  function next() { show(current + 1); }
  function prev() { show(current - 1); }

  // start() clears any old timer first, so timers never stack up
  function start() {
    stop();
    timer = setInterval(next, INTERVAL);
  }

  function stop() {
    clearInterval(timer);
  }

  // Arrow buttons
  root.querySelector('.next').addEventListener('click', () => { next(); start(); });
  root.querySelector('.prev').addEventListener('click', () => { prev(); start(); });

  // Pause while the mouse is over the slider or a finger is touching it,
  // so visitors have time to read
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('touchstart', stop, { passive: true });
  root.addEventListener('touchend', start);

  show(0);
  start();
}

// Start the slider once the page has loaded
document.addEventListener('DOMContentLoaded', () => {
  const slider = document.getElementById('hero-slider');
  if (slider) initSlider(slider);
});