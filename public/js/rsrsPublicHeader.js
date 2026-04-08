const navToggle = document.getElementById('navToggle');
    const mainNav = document.getElementById('mainNav');

    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      mainNav.classList.toggle('show');
      navToggle.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', mainNav.classList.contains('show') ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
      if (!mainNav.contains(e.target) && !navToggle.contains(e.target)) {
        mainNav.classList.remove('show');
        navToggle.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });


