<?php
/**
 * Admin Class - ВИПРАВЛЕНА ВЕРСІЯ
 *
 * @package RP_Payparts
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Адмін-функціонал
 */
class RP_Payparts_Admin {

	/**
	 * Конструктор
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'plugin_action_links_' . RP_PAYPARTS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		add_action( 'woocommerce_admin_order_data_after_payment_info', array( $this, 'display_order_payment_info' ) );

		// ✅ ДОДАЄМО AJAX-ДІЇ
		add_action( 'wp_ajax_rp_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_rp_clear_cache', array( $this, 'ajax_clear_cache' ) );
	}

	/**
	 * Додавання меню в адмінці
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'RozetkaPay Оплата частинами', 'rozetkapay-payparts' ),
			__( 'RozetkaPay Частинами', 'rozetkapay-payparts' ),
			'manage_woocommerce',
			'rp-payparts',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Адмін-сторінка
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Access denied', 'rozetkapay-payparts' ) );
		}

		$active_tab = 'overview';
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rp_payparts_admin_tab' ) && isset( $_GET['tab'] ) ) {
			$requested = sanitize_key( wp_unslash( $_GET['tab'] ) );
			if ( in_array( $requested, array( 'overview', 'orders', 'settings' ), true ) ) {
				$active_tab = $requested;
			}
		}

		$overview_url = wp_nonce_url( admin_url( 'admin.php?page=rp-payparts&tab=overview' ), 'rp_payparts_admin_tab' );
		$orders_url   = wp_nonce_url( admin_url( 'admin.php?page=rp-payparts&tab=orders' ), 'rp_payparts_admin_tab' );
		$settings_url = wp_nonce_url( admin_url( 'admin.php?page=rp-payparts&tab=settings' ), 'rp_payparts_admin_tab' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RozetkaPay Оплата частинами', 'rozetkapay-payparts' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $overview_url ); ?>"
					class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Огляд', 'rozetkapay-payparts' ); ?>
				</a>
				<a href="<?php echo esc_url( $orders_url ); ?>"
					class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Замовлення', 'rozetkapay-payparts' ); ?>
				</a>
				<a href="<?php echo esc_url( $settings_url ); ?>"
					class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Налаштування', 'rozetkapay-payparts' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'orders':
						$this->render_orders_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					default:
						$this->render_overview_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Вивід вкладки «Огляд» – ОНОВЛЕНА ВЕРСІЯ для двох методів
	 */
	private function render_overview_tab() {
		$payparts_gateway = rp_payparts_get_gateway();
		$standard_gateway = $this->get_standard_gateway();
		$stats            = $this->get_payment_stats();
		?>
		<div class="rp-admin-overview">
			<div class="rp-stats-grid">
				<div class="rp-stat-card">
					<h3><?php esc_html_e( 'Всього платежів', 'rozetkapay-payparts' ); ?></h3>
					<div class="rp-stat-number"><?php echo esc_html( $stats['total_payments'] ); ?></div>
					<div class="rp-stat-breakdown">
						<?php echo esc_html( $stats['payparts_payments'] ); ?> частинами |
						<?php echo esc_html( $stats['standard_payments'] ); ?> картками
					</div>
				</div>

				<div class="rp-stat-card">
					<h3><?php esc_html_e( 'Успішних платежів', 'rozetkapay-payparts' ); ?></h3>
					<div class="rp-stat-number"><?php echo esc_html( $stats['successful_payments'] ); ?></div>
					<div class="rp-stat-breakdown">
						<?php echo esc_html( $stats['successful_payparts'] ); ?> частинами |
						<?php echo esc_html( $stats['successful_standard'] ); ?> картками
					</div>
				</div>

				<div class="rp-stat-card">
					<h3><?php esc_html_e( 'Загальна сума', 'rozetkapay-payparts' ); ?></h3>
					<div class="rp-stat-number"><?php echo wp_kses_post( wc_price( $stats['total_amount'] ) ); ?></div>
					<div class="rp-stat-breakdown">
						<?php echo wp_kses_post( wc_price( $stats['payparts_amount'] ) ); ?> частинами<br>
						<?php echo wp_kses_post( wc_price( $stats['standard_amount'] ) ); ?> картками
					</div>
				</div>

				<div class="rp-stat-card">
					<h3><?php esc_html_e( 'Очікують оплати', 'rozetkapay-payparts' ); ?></h3>
					<div class="rp-stat-number"><?php echo esc_html( $stats['pending_payments'] ); ?></div>
					<div class="rp-stat-breakdown">
						<?php echo esc_html( $stats['pending_payparts'] ); ?> частинами |
						<?php echo esc_html( $stats['pending_standard'] ); ?> картками
					</div>
				</div>
			</div>

			<!-- Статус методів оплати -->
			<div class="rp-payment-methods-status">
				<h3><?php esc_html_e( 'Статус методів оплати', 'rozetkapay-payparts' ); ?></h3>

				<div class="rp-methods-grid">
					<!-- RozetkaPay Payparts -->
					<div class="rp-method-card <?php echo $payparts_gateway && 'yes' === $payparts_gateway->enabled ? 'rp-status-active' : 'rp-status-inactive'; ?>">
						<h4>💳 RozetkaPay Оплата частинами</h4>
						<?php if ( $payparts_gateway && 'yes' === $payparts_gateway->enabled ) : ?>
							<p>✅ <?php esc_html_e( 'Активний і працює', 'rozetkapay-payparts' ); ?></p>
							<p><strong><?php esc_html_e( 'Режим:', 'rozetkapay-payparts' ); ?></strong>
								<?php echo $payparts_gateway->test_mode ? esc_html__( 'Продакшн', 'rozetkapay-payparts' ) : esc_html__( 'Продакшн', 'rozetkapay-payparts' ); ?>
							</p>
						<?php else : ?>
							<p>⚠️ <?php esc_html_e( 'Неактивний', 'rozetkapay-payparts' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>"
								class="button button-primary">
								<?php esc_html_e( 'Активувати', 'rozetkapay-payparts' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<!-- RozetkaPay Standard -->
					<div class="rp-method-card <?php echo $standard_gateway && 'yes' === $standard_gateway->enabled ? 'rp-status-active' : 'rp-status-inactive'; ?>">
						<h4>🏦 RozetkaPay Банківські картки</h4>
						<?php if ( $standard_gateway && 'yes' === $standard_gateway->enabled ) : ?>
							<p>✅ <?php esc_html_e( 'Активний і працює', 'rozetkapay-payparts' ); ?></p>
							<p><strong><?php esc_html_e( 'Режим:', 'rozetkapay-payparts' ); ?></strong>
								<?php echo $standard_gateway->test_mode ? esc_html__( 'Продакшн', 'rozetkapay-payparts' ) : esc_html__( 'Продакшн', 'rozetkapay-payparts' ); ?>
							</p>
							<p><strong><?php esc_html_e( 'Тип:', 'rozetkapay-payparts' ); ?></strong>
								<?php echo isset( $standard_gateway->two_step_payment ) && $standard_gateway->two_step_payment ? esc_html__( 'Двоетапна оплата', 'rozetkapay-payparts' ) : esc_html__( 'Одноетапна оплата', 'rozetkapay-payparts' ); ?>
							</p>
						<?php else : ?>
							<p>⚠️ <?php esc_html_e( 'Неактивний', 'rozetkapay-payparts' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_standard' ) ); ?>"
								class="button button-primary">
								<?php esc_html_e( 'Активувати', 'rozetkapay-payparts' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="rp-quick-actions">
				<h3><?php esc_html_e( 'Швидкі дії', 'rozetkapay-payparts' ); ?></h3>
				<div class="rp-actions-grid">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>"
						class="button">
						<?php esc_html_e( 'Налаштування частинами', 'rozetkapay-payparts' ); ?>
					</a>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_standard' ) ); ?>"
						class="button">
						<?php esc_html_e( 'Налаштування картками', 'rozetkapay-payparts' ); ?>
					</a>

					<button type="button" class="button" id="rp-test-connection" data-nonce="<?php echo esc_attr( wp_create_nonce( 'rp_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Тест підключення API', 'rozetkapay-payparts' ); ?>
					</button>

					<button type="button" class="button" id="rp-clear-cache" data-nonce="<?php echo esc_attr( wp_create_nonce( 'rp_admin_nonce' ) ); ?>">
						<?php esc_html_e( 'Очистити кеш', 'rozetkapay-payparts' ); ?>
					</button>
				</div>

				<!-- Область для повідомлень -->
				<div id="rp-admin-messages" class="rp-admin-messages-wrap"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Вивід вкладки «Замовлення»
	 */
	private function render_orders_tab() {
		$orders = $this->get_payparts_orders();
		?>
		<div class="rp-orders-tab">
			<h3><?php esc_html_e( 'Замовлення з оплатою частинами', 'rozetkapay-payparts' ); ?></h3>

			<?php if ( empty( $orders ) ) : ?>
				<p><?php esc_html_e( 'Замовлень з оплатою частинами поки немає.', 'rozetkapay-payparts' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Замовлення', 'rozetkapay-payparts' ); ?></th>
							<th><?php esc_html_e( 'Клієнт', 'rozetkapay-payparts' ); ?></th>
							<th><?php esc_html_e( 'Сума', 'rozetkapay-payparts' ); ?></th>
							<th><?php esc_html_e( 'Банк', 'rozetkapay-payparts' ); ?></th>
							<th><?php esc_html_e( 'Термін', 'rozetkapay-payparts' ); ?></th>
							<th><?php esc_html_e( 'Статус', 'rozetkapay-payparts' ); ?></th>
							<th><?php esc_html_e( 'Дата', 'rozetkapay-payparts' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orders as $order ) : ?>
							<?php $option_data = rp_payparts_parse_option( get_post_meta( $order->get_id(), '_rp_payparts_option', true ) ); ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
										#<?php echo esc_html( $order->get_order_number() ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td><?php echo $option_data ? esc_html( $option_data['bank_name'] ) : '—'; ?></td>
								<td><?php echo $option_data ? esc_html( $option_data['term_text'] ) : '—'; ?></td>
								<td>
									<span class="rp-status rp-status-<?php echo esc_attr( $order->get_status() ); ?>">
										<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Вивід вкладки «Налаштування»
	 */
	private function render_settings_tab() {
		?>
		<div class="rp-settings-tab">
			<p><?php esc_html_e( 'Налаштування плагіна доступні в', 'rozetkapay-payparts' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>">
					<?php esc_html_e( 'налаштуваннях WooCommerce', 'rozetkapay-payparts' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Отримати стандартний gateway – НОВИЙ МЕТОД
	 */
	private function get_standard_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
			return null;
		}

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		return isset( $gateways['rp_standard'] ) ? $gateways['rp_standard'] : null;
	}

	/**
	 * Отримати статистику платежів – ОНОВЛЕНА ВЕРСІЯ для двох методів
	 */
	private function get_payment_stats() {
		// Отримуємо замовлення для обох методів
		$payparts_orders = wc_get_orders(
			array(
				'payment_method' => 'rp_payparts',
				'limit'          => -1,
			)
		);

		$standard_orders = wc_get_orders(
			array(
				'payment_method' => 'rp_standard',
				'limit'          => -1,
			)
		);

		$stats = array(
			'total_payments'      => count( $payparts_orders ) + count( $standard_orders ),
			'payparts_payments'   => count( $payparts_orders ),
			'standard_payments'   => count( $standard_orders ),
			'successful_payments' => 0,
			'successful_payparts' => 0,
			'successful_standard' => 0,
			'total_amount'        => 0,
			'payparts_amount'     => 0,
			'standard_amount'     => 0,
			'pending_payments'    => 0,
			'pending_payparts'    => 0,
			'pending_standard'    => 0,
		);

		// Обробляємо замовлення Payparts
		foreach ( $payparts_orders as $order ) {
			$stats['payparts_amount'] += $order->get_total();

			if ( $order->has_status( array( 'completed', 'processing' ) ) ) {
				++$stats['successful_payparts'];
			} elseif ( $order->has_status( 'on-hold' ) ) {
				++$stats['pending_payparts'];
			}
		}

		// Обробляємо замовлення Standard
		foreach ( $standard_orders as $order ) {
			$stats['standard_amount'] += $order->get_total();

			if ( $order->has_status( array( 'completed', 'processing' ) ) ) {
				++$stats['successful_standard'];
			} elseif ( $order->has_status( array( 'on-hold', 'pending' ) ) ) {
				++$stats['pending_standard'];
			}
		}

		// Підрахунок загальних значень
		$stats['successful_payments'] = $stats['successful_payparts'] + $stats['successful_standard'];
		$stats['total_amount']        = $stats['payparts_amount'] + $stats['standard_amount'];
		$stats['pending_payments']    = $stats['pending_payparts'] + $stats['pending_standard'];

		return $stats;
	}

	/**
	 * Отримати замовлення з оплатою частинами
	 */
	private function get_payparts_orders() {
		return wc_get_orders(
			array(
				'payment_method' => 'rp_payparts',
				'limit'          => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * AJAX: Тест підключення до API
	 */
	public function ajax_test_connection() {
		// Перевірка nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rp_admin_nonce' ) ) {
			wp_send_json_error( __( 'Помилка безпеки', 'rozetkapay-payparts' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Недостатньо прав доступу', 'rozetkapay-payparts' ) );
		}

		$gateway = rp_payparts_get_gateway();
		if ( ! $gateway ) {
			wp_send_json_error( __( 'Шлюз не знайдено', 'rozetkapay-payparts' ) );
		}

		// Створюємо API-клієнт
		$api_client = new RP_Payparts_API( $gateway->api_user, $gateway->api_pass, false );

		// Отримуємо результат тесту
		$result = $api_client->test_connection();

		if ( $result['success'] ) {
			// Додатково пробуємо отримати банки для детальнішої інформації
			$banks = $api_client->fetch_banks();
			if ( ! is_wp_error( $banks ) && is_array( $banks ) ) {
				$result['banks_count'] = count( $banks );

				// Оновлюємо кеш
				set_transient( 'rp_payparts_banks', $banks, HOUR_IN_SECONDS );
			}

			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX: Очищення кешу
	 */
	public function ajax_clear_cache() {
		// Перевірка nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rp_admin_nonce' ) ) {
			wp_send_json_error( __( 'Помилка безпеки', 'rozetkapay-payparts' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Недостатньо прав доступу', 'rozetkapay-payparts' ) );
		}

		// Очищаємо всі transients плагіна
		rp_payparts_clear_cache();

		// Додатково очищаємо специфічні кеші
		delete_transient( 'rp_payparts_banks' );
		delete_transient( 'rozetkapay_payparts_banks' );

		// Очищаємо кеш об'єктів WordPress
		wp_cache_flush();

		wp_send_json_success( __( 'Кеш успішно очищено', 'rozetkapay-payparts' ) );
	}

	/**
	 * Посилання дій плагіна на сторінці плагінів
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ) . '">' . __( 'Налаштування', 'rozetkapay-payparts' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Відображення інформації про оплату в замовленні
	 */
	public function display_order_payment_info( $order ) {
		if ( $order->get_payment_method() !== 'rp_payparts' ) {
			return;
		}

		$payment_data     = rp_payparts_get_order_payment_data( $order->get_id() );
		$installment_info = rp_payparts_get_order_installment_summary( $order->get_id() );

		if ( ! $payment_data && ! $installment_info ) {
			return;
		}

		?>
		<div class="rp-order-payment-info">
			<h4><?php esc_html_e( 'Інформація про оплату частинами', 'rozetkapay-payparts' ); ?></h4>

			<?php if ( $installment_info ) : ?>
				<p><strong><?php esc_html_e( 'Банк:', 'rozetkapay-payparts' ); ?></strong> <?php echo esc_html( $installment_info['bank_name'] ); ?></p>
				<p><strong><?php esc_html_e( 'Термін:', 'rozetkapay-payparts' ); ?></strong> <?php echo esc_html( $installment_info['term_text'] ); ?></p>
				<p><strong><?php esc_html_e( 'Щомісячний платіж:', 'rozetkapay-payparts' ); ?></strong> <?php echo esc_html( $installment_info['monthly_payment_formatted'] ); ?></p>
			<?php endif; ?>

			<?php if ( $payment_data['payment_id'] ) : ?>
				<p><strong><?php esc_html_e( 'ID операції:', 'rozetkapay-payparts' ); ?></strong> <code><?php echo esc_html( $payment_data['payment_id'] ); ?></code></p>
			<?php endif; ?>

			<?php if ( $payment_data['transaction_id'] ) : ?>
				<p><strong><?php esc_html_e( 'ID транзакції:', 'rozetkapay-payparts' ); ?></strong> <code><?php echo esc_html( $payment_data['transaction_id'] ); ?></code></p>
			<?php endif; ?>

			<?php if ( $payment_data['payment_url'] && $order->has_status( 'on-hold' ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Посилання для оплати:', 'rozetkapay-payparts' ); ?></strong><br>
					<a href="<?php echo esc_url( $payment_data['payment_url'] ); ?>" target="_blank" class="button button-small">
						<?php esc_html_e( 'Відкрити сторінку оплати', 'rozetkapay-payparts' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Адмін-повідомлення
	 */
	public function admin_notices() {
		// Перевіряємо, чи активний WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: %s: Plugin name */
						esc_html__( '%s потребує активного WooCommerce для роботи.', 'rozetkapay-payparts' ),
						'<strong>RozetkaPay Payparts</strong>'
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		// Перевіряємо, чи налаштований шлюз
		$gateway = rp_payparts_get_gateway();
		if ( $gateway && 'yes' === $gateway->enabled && ( empty( $gateway->api_user ) || empty( $gateway->api_pass ) ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php esc_html_e( 'RozetkaPay Payparts: Будь ласка, налаштуйте API ключі для роботи плагіна.', 'rozetkapay-payparts' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rp_payparts' ) ); ?>">
						<?php esc_html_e( 'Налаштувати зараз', 'rozetkapay-payparts' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}
}

// Ініціалізація адмін-класу в адмін-панелі
if ( is_admin() ) {
	new RP_Payparts_Admin();
}
