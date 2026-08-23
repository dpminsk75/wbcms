/* =========================================================================
   MOBILE-NAV.JS — логика открытия/закрытия мобильного гамбургер-меню
   Подключается в views/layouts/main.php через registerJsFile().
   ========================================================================= */
document.addEventListener('DOMContentLoaded', function () {
    var toggleBtn = document.getElementById('mobileMenuToggle');
    var drawer    = document.getElementById('mobileNavDrawer');
    var overlay   = document.getElementById('mobileMenuOverlay');

    if (!toggleBtn || !drawer || !overlay) { return; }

    function openMenu() {
        drawer.classList.add('is-open');
        overlay.classList.add('is-visible');
        toggleBtn.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        toggleBtn.setAttribute('aria-expanded', 'false');
    }

    toggleBtn.addEventListener('click', function () {
        if (drawer.classList.contains('is-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    overlay.addEventListener('click', closeMenu);

    // Закрываем меню при переходе по любому пункту
    drawer.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });
});
