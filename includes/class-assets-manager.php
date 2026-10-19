<?php
/**
 * Менеджер ресурсів (Assets Manager) для RozetkaPay Payparts
 * Керування CSS та JS файлами плагіна
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RozetkaPay_Payparts_Assets_Manager {

	/**
	 * Конструктор
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Підключення стилів для фронтенду
	 */
	public function enqueue_frontend_styles() {
		// Підключаємо тільки на потрібних сторінках
		if ( ! $this->should_load_frontend_assets() ) {
			return;
		}

		// Основні стилі
		wp_enqueue_style(
			'rozetkapay-payparts-frontend',
			ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$this->get_asset_version( 'assets/css/frontend.css' )
		);

		// Додаткові стилі для мобільної версії
		wp_enqueue_style(
			'rozetkapay-payparts-mobile',
			ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/css/mobile.css',
			array( 'rozetkapay-payparts-frontend' ),
			$this->get_asset_version( 'assets/css/mobile.css' )
		);

		// Slider CSS — підключаємо ЗАВЖДИ на checkout/cart через Assets Manager.
		// Виклик wp_enqueue_style() всередині render_slider_interface() відбувається
		// після wp_head() і браузер ігнорує ці стилі — саме тому картки не мали CSS.
		wp_enqueue_style(
			'rozetkapay-payparts-slider',
			ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/css/slider.css',
			array( 'rozetkapay-payparts-frontend' ),
			$this->get_asset_version( 'assets/css/slider.css' )
		);

		wp_enqueue_style(
			'rozetkapay-payparts-widget',
			ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/css/widget.css',
			array( 'rozetkapay-payparts-frontend' ),
			$this->get_asset_version( 'assets/css/widget.css' )
		);

		// Підключаємо JavaScript для checkout
		if ( is_checkout() ) {
			wp_enqueue_script(
				'rozetkapay-payparts-checkout',
				ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				$this->get_asset_version( 'assets/js/frontend.js' ),
				true
			);

			// Локалізація для JS
			wp_localize_script(
				'rozetkapay-payparts-checkout',
				'rozetkapayPayparts',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'rozetkapay_payparts_nonce' ),
					'assetsUrl'      => ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/',
					'enableAdvanced' => false, // Увімкнути розширені функції
					'strings'        => array(
						'selectOption' => __( 'Будь ласка, оберіть варіант оплати', 'rozetkapay-payparts' ),
						'calculating'  => __( 'Розраховуємо...', 'rozetkapay-payparts' ),
						'error'        => __( 'Виникла помилка', 'rozetkapay-payparts' ),
					),
				)
			);
		}
	}

	/**
	 * Підключення стилів для адмінки
	 */
	public function enqueue_admin_styles( $hook ) {
		// Підключаємо тільки на сторінках налаштувань WooCommerce
		if ( ! $this->should_load_admin_assets( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'rozetkapay-payparts-admin',
			ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->get_asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'rozetkapay-payparts-admin',
			ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->get_asset_version( 'assets/js/admin.js' ),
			true
		);

		// Локалізація для адмін JS
		wp_localize_script(
			'rozetkapay-payparts-admin',
			'rozetkapayPaypartsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rozetkapay_payparts_admin_nonce' ),
				'strings' => array(
					'confirmDelete'   => __( 'Ви впевнені?', 'rozetkapay-payparts' ),
					'saved'           => __( 'Збережено', 'rozetkapay-payparts' ),
					'error'           => __( 'Помилка', 'rozetkapay-payparts' ),
					'testing'         => __( 'Тестування...', 'rozetkapay-payparts' ),
					'testApi'         => __( 'Тест підключення API', 'rozetkapay-payparts' ),
					'testingApi'      => __( 'Тестуємо підключення до API...', 'rozetkapay-payparts' ),
					'connectionError' => __( 'Помилка підключення', 'rozetkapay-payparts' ),
					'banksFound'      => __( 'Знайдено банків:', 'rozetkapay-payparts' ),
					'clearing'        => __( 'Очищаємо...', 'rozetkapay-payparts' ),
					'clearingCache'   => __( 'Очищаємо кеш...', 'rozetkapay-payparts' ),
					'clearCache'      => __( 'Очистити кеш', 'rozetkapay-payparts' ),
					'cacheClearError' => __( 'Помилка очищення кешу', 'rozetkapay-payparts' ),
				),
			)
		);
	}

	/**
	 * Перевірка чи потрібно завантажувати фронтенд ресурси
	 */
	private function should_load_frontend_assets() {
		// Завантажуємо тільки на специфічних сторінках
		if ( is_cart() || is_checkout() ) {
			return true;
		}

		// Перевіряємо ендпоінти WooCommerce
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return true;
		}

		// Перевіряємо шорткоди WooCommerce
		if ( is_singular() ) {
			global $post;
			if ( $post && (
				has_shortcode( $post->post_content, 'woocommerce_checkout' ) ||
				has_shortcode( $post->post_content, 'woocommerce_cart' ) ||
				has_shortcode( $post->post_content, 'rozetkapay_payparts_widget' ) ||
				has_shortcode( $post->post_content, 'rp_payparts_widget' )
			) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Перевірка чи потрібно завантажувати адмін ресурси
	 */
	private function should_load_admin_assets( $hook ) {
		// Сторінка RozetkaPay в меню WooCommerce
		if ( 'woocommerce_page_rozetkapay-payparts' === $hook ) {
			return true;
		}

		// Сторінка редагування замовлення (класична та HPOS)
		if ( in_array( $hook, array( 'post.php', 'woocommerce_page_wc-orders' ), true ) ) {
			return true;
		}

		// Сторінки налаштувань WooCommerce
		$wc_admin_pages = array(
			'woocommerce_page_wc-settings',
			'woocommerce_page_wc-status',
		);

		if ( in_array( $hook, $wc_admin_pages, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Отримати версію файлу для cache busting
	 */
	private function get_asset_version( $asset_path ) {
		$full_path = ROZETKAPAY_PAYPARTS_PLUGIN_PATH . $asset_path;

		if ( file_exists( $full_path ) ) {
			return filemtime( $full_path );
		}

		return ROZETKAPAY_PAYPARTS_VERSION;
	}

	/**
	 * Перевірка наявності критично важливих файлів
	 */
	public function check_critical_assets() {
		$critical_files = array(
			'assets/css/frontend.css',
			'assets/js/frontend.js',
		);

		$missing_files = array();

		foreach ( $critical_files as $file ) {
			if ( ! file_exists( ROZETKAPAY_PAYPARTS_PLUGIN_PATH . $file ) ) {
				$missing_files[] = $file;
			}
		}

		if ( ! empty( $missing_files ) && current_user_can( 'manage_options' ) ) {
			add_action(
				'admin_notices',
				function () use ( $missing_files ) {
					echo '<div class="notice notice-warning is-dismissible">';
					echo '<p><strong>RozetkaPay Payparts:</strong> ';
					printf(
						// translators: %s: comma-separated list of missing plugin files.
						esc_html__( 'Відсутні критичні файли: %s', 'rozetkapay-payparts' ),
						esc_html( implode( ', ', $missing_files ) )
					);
					echo '</p></div>';
				}
			);
		}

		return empty( $missing_files );
	}
}
