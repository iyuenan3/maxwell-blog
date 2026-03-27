/**
 * CareerCompass Theme Navigation
 *
 * @package CareerCompass
 * @since 1.0.0
 */

(function() {
	'use strict';

	// 移动端菜单切换
	const menuToggle = document.querySelector('.mobile-menu-toggle');
	const mainMenu = document.querySelector('.cc-navbar-menu');

	if ( menuToggle && mainMenu ) {
		menuToggle.addEventListener('click', function() {
			mainMenu.classList.toggle('active');
			menuToggle.setAttribute(
				'aria-expanded',
				menuToggle.getAttribute('aria-expanded') === 'false' ? 'true' : 'false'
			);
		});
	}

	// 平滑滚动
	document.querySelectorAll('a[href^="#"]').forEach(anchor => {
		anchor.addEventListener('click', function (e) {
			const targetId = this.getAttribute('href');
			if ( targetId === '#' ) return;

			const target = document.querySelector(targetId);
			if ( target ) {
				e.preventDefault();
				target.scrollIntoView({
					behavior: 'smooth'
				});
			}
		});
	});

	// 导航栏滚动效果
	let lastScroll = 0;
	const navbar = document.getElementById('masthead');

	if ( navbar ) {
		window.addEventListener('scroll', function() {
			const currentScroll = window.pageYOffset;

			if ( currentScroll > 100 ) {
				navbar.style.boxShadow = 'var(--cc-shadow-md)';
			} else {
				navbar.style.boxShadow = 'var(--cc-shadow-sm)';
			}

			lastScroll = currentScroll;
		});
	}
})();
