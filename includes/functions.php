<?php
/**
 * RozetkaPay Payparts Helper Functions - ВИПРАВЛЕНА ВЕРСІЯ
 * Допоміжні функції для плагіна
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rp_payparts_get_month_declension' ) ) {
	/**
	 * Отримати правильне відмінювання слова "місяць"
	 *
	 * @param int $number Кількість місяців
	 * @return string Правильне відмінювання
	 */
	function rp_payparts_get_month_declension( $number ) {
		$cases  = array( 2, 0, 1, 1, 1, 2 );
		$titles = array(
			__( 'місяць', 'rp-payparts' ),
			__( 'місяці', 'rp-payparts' ),
			__( 'місяців', 'rp-payparts' ),
		);

		return $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
	}
}

if ( ! function_exists( 'rp_payparts_get_bank_name' ) ) {
	/**
	 * Отримати зручну для користувача назву банку за slug
	 *
	 * @param string $bank_slug Slug банку
	 * @return string Назва банку
	 */
	function rp_payparts_get_bank_name( $bank_slug ) {
		// ✅ ОНОВЛЕНИЙ список реальних банків RozetkaPay API
		$bank_names = array(
			'abank'      => 'ABank',           // ✅ ДОДАНО
			'monobank'   => 'Monobank',
			'privatbank' => 'ПриватБанк',
			'rozetkapay' => 'RozetkaPay',
			'izibank'    => 'izibank',
		);

		return isset( $bank_names[ $bank_slug ] )
			? $bank_names[ $bank_slug ]
			: ucfirst( str_replace( array( '-', '_' ), ' ', $bank_slug ) );
	}
}

if ( ! function_exists( 'rp_payparts_get_bank_min_period' ) ) {
	/**
	 * Отримати мінімальний період для банку – НОВА ФУНКЦІЯ
	 *
	 * @param string $bank_slug Slug банку
	 * @return int Мінімальний період у місяцях
	 */
	function rp_payparts_get_bank_min_period( $bank_slug ) {
		$bank_min_periods = array(
			'monobank'   => 3,     // Monobank мінімум 3 місяці
			'izibank'    => 3,      // izibank мінімум 3 місяці
			'abank'      => 2,        // Abank мінімум 2 місяці
			'privatbank' => 2,   // ПриватБанк може від 2
			'rozetkapay' => 2,   // RozetkaPay може від 2
		);

		return isset( $bank_min_periods[ $bank_slug ] ) ? $bank_min_periods[ $bank_slug ] : 3;
	}
}

if ( ! function_exists( 'rp_payparts_validate_bank_terms' ) ) {
	/**
	 * Валідація списку періодів для банку – НОВА ФУНКЦІЯ
	 *
	 * @param string $bank_slug Slug банку
	 * @param array  $periods Масив періодів
	 * @return array Відфільтрований масив періодів
	 */
	function rp_payparts_validate_bank_terms( $bank_slug, $periods ) {
		$min_period = rp_payparts_get_bank_min_period( $bank_slug );

		return array_filter(
			$periods,
			function ( $period ) use ( $min_period ) {
				return intval( $period ) >= $min_period && intval( $period ) <= 25;
			}
		);
	}
}

if ( ! function_exists( 'rp_payparts_format_currency' ) ) {
	/**
	 * Форматування валюти
	 *
	 * @param float  $amount Сума
	 * @param string $currency Код валюти
	 * @return string Відформатована сума
	 */
	function rp_payparts_format_currency( $amount, $currency = 'UAH' ) {
		$formatted = number_format( $amount, 0, ',', ' ' );

		switch ( $currency ) {
			case 'UAH':
				return $formatted . ' грн';
			case 'USD':
				return '$' . $formatted;
			case 'EUR':
				return '€' . $formatted;
			default:
				return $formatted . ' ' . $currency;
		}
	}
}

if ( ! function_exists( 'rp_payparts_log' ) ) {
	/**
	 * Логування подій плагіна
	 *
	 * @param string $message Повідомлення для лога
	 * @param string $level Рівень лога (info, warning, error)
	 */
	function rp_payparts_log( $message, $level = 'info' ) {
		if ( ! WP_DEBUG_LOG ) {
			return;
		}

		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$log_message = sprintf( '[%s] [RozetkaPay Payparts] [%s] %s', $timestamp, strtoupper( $level ), $message );

		error_log( $log_message );
	}
}

if ( ! function_exists( 'rp_payparts_is_plugin_active' ) ) {
	/**
	 * Перевірити, чи активний плагін
	 *
	 * @return bool
	 */
	function rp_payparts_is_plugin_active() {
		return class_exists( 'RP_Payparts_Gateway' );
	}
}

if ( ! function_exists( 'rp_payparts_get_gateway' ) ) {
	/**
	 * Отримати екземпляр gateway
	 *
	 * @return RP_Payparts_Gateway|null
	 */
	function rp_payparts_get_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
			return null;
		}

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		return isset( $gateways['rp_payparts'] ) ? $gateways['rp_payparts'] : null;
	}
}

if ( ! function_exists( 'rp_get_payment_method_type' ) ) {
	/**
	 * Визначити тип методу оплати за замовленням – НОВА ФУНКЦІЯ
	 *
	 * @param WC_Order $order Замовлення
	 * @return string|null Тип методу ('payparts' або 'standard')
	 */
	function rp_get_payment_method_type( $order ) {
		if ( ! $order ) {
			return null;
		}

		$payment_method = $order->get_payment_method();

		switch ( $payment_method ) {
			case 'rp_payparts':
				return 'payparts';
			case 'rp_standard':
				return 'standard';
			default:
				return null;
		}
	}
}

if ( ! function_exists( 'rp_get_payment_method_title' ) ) {
	/**
	 * Отримати назву методу оплати – НОВА ФУНКЦІЯ
	 *
	 * @param string $method Тип методу ('payparts' або 'standard')
	 * @return string
	 */
	function rp_get_payment_method_title( $method ) {
		switch ( $method ) {
			case 'payparts':
				return __( 'RozetkaPay Оплата частинами', 'rp-payparts' );
			case 'standard':
				return __( 'RozetkaPay Банківські картки', 'rp-payparts' );
			default:
				return __( 'RozetkaPay', 'rp-payparts' );
		}
	}
}

if ( ! function_exists( 'rp_payparts_calculate_installment' ) ) {
	/**
	 * Розрахувати розмір щомісячного платежу
	 *
	 * @param float $total Загальна сума
	 * @param int   $months Кількість місяців
	 * @return float Розмір щомісячного платежу
	 */
	function rp_payparts_calculate_installment( $total, $months ) {
		if ( $months <= 0 ) {
			return 0;
		}

		return round( $total / $months, 2 );
	}
}

if ( ! function_exists( 'rp_payparts_get_available_banks' ) ) {
	/**
	 * Отримати список доступних банків
	 *
	 * @return array
	 */
	function rp_payparts_get_available_banks() {
		$gateway = rp_payparts_get_gateway();

		if ( ! $gateway ) {
			return array();
		}

		// ✅ ОНОВЛЕНІ тестові банки (відповідають реальному API)
		if ( $gateway->test_mode ) {
			return array(
				'abank'      => 'ABank',           // ✅ ДОДАНО
				'monobank'   => 'Monobank',
				'privatbank' => 'ПриватБанк',
				'rozetkapay' => 'RozetkaPay',
				'izibank'    => 'izibank',
			);
		}

		// Отримуємо з кешу або API
		$banks = get_transient( 'rp_payparts_banks' );
		if ( false === $banks && method_exists( $gateway, 'get_api_client' ) ) {
			$api   = $gateway->get_api_client();
			$banks = $api->fetch_banks();

			if ( ! empty( $banks ) && ! is_wp_error( $banks ) ) {
				set_transient( 'rp_payparts_banks', $banks, HOUR_IN_SECONDS );
			}
		}

		return is_array( $banks ) ? $banks : array();
	}
}

if ( ! function_exists( 'rp_payparts_clear_cache' ) ) {
	/**
	 * Очистити кеш плагіна
	 */
	function rp_payparts_clear_cache() {
		delete_transient( 'rp_payparts_banks' );
		delete_transient( 'rozetkapay_payparts_banks' ); // Старий ключ для сумісності

		rp_payparts_log( 'Cache cleared' );
	}
}

if ( ! function_exists( 'rp_payparts_get_plugin_version' ) ) {
	/**
	 * Отримати версію плагіна
	 *
	 * @return string
	 */
	function rp_payparts_get_plugin_version() {
		return defined( 'RP_PAYPARTS_VERSION' ) ? RP_PAYPARTS_VERSION : '2.0.2';
	}
}

if ( ! function_exists( 'rp_payparts_is_woocommerce_active' ) ) {
	/**
	 * Перевірити, чи активний WooCommerce
	 *
	 * @return bool
	 */
	function rp_payparts_is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}

if ( ! function_exists( 'rp_payparts_get_order_payment_data' ) ) {
	/**
	 * Отримати метадані замовлення, пов’язані з розстрочкою
	 *
	 * @param int $order_id ID замовлення
	 * @return array
	 */
	function rp_payparts_get_order_payment_data( $order_id ) {
		return array(
			'option'         => get_post_meta( $order_id, '_rp_payparts_option', true ),
			'payment_id'     => get_post_meta( $order_id, '_rp_payparts_payment_id', true ),
			'payment_url'    => get_post_meta( $order_id, '_rp_payparts_payment_url', true ),
			'transaction_id' => get_post_meta( $order_id, '_rp_payparts_transaction_id', true ),
			'bank'           => get_post_meta( $order_id, '_rp_payparts_bank', true ),
			'term'           => get_post_meta( $order_id, '_rp_payparts_term', true ),
		);
	}
}

if ( ! function_exists( 'rp_payparts_parse_option' ) ) {
	/**
	 * Парсинг опції розстрочки – ОНОВЛЕНА ВЕРСІЯ
	 *
	 * @param string $option Опція у форматі "bank|term"
	 * @return array|null
	 */
	function rp_payparts_parse_option( $option ) {
		if ( empty( $option ) || ! strpos( $option, '|' ) ) {
			return null;
		}

		$parts = explode( '|', $option );
		if ( count( $parts ) !== 2 ) {
			return null;
		}

		$bank_slug = sanitize_text_field( $parts[0] );
		$term      = intval( $parts[1] );

		if ( empty( $bank_slug ) || $term <= 0 ) {
			return null;
		}

		// ✅ Використовуємо нову функцію отримання назви банку
		$bank_name = rp_payparts_get_bank_name( $bank_slug );

		return array(
			'bank_slug' => $bank_slug,
			'bank_name' => $bank_name,
			'term'      => $term,
			'term_text' => $term . ' ' . rp_payparts_get_month_declension( $term ),
		);
	}
}

if ( ! function_exists( 'rp_payparts_get_order_installment_summary' ) ) {
	/**
	 * Отримати зведення по розстрочці для замовлення
	 *
	 * @param int $order_id ID замовлення
	 * @return array|null
	 */
	function rp_payparts_get_order_installment_summary( $order_id ) {
		$option = get_post_meta( $order_id, '_rp_payparts_option', true );
		$parsed = rp_payparts_parse_option( $option );

		if ( ! $parsed ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$monthly_payment = rp_payparts_calculate_installment( $order->get_total(), $parsed['term'] );

		return array_merge(
			$parsed,
			array(
				'monthly_payment'           => $monthly_payment,
				'monthly_payment_formatted' => rp_payparts_format_currency( $monthly_payment ),
				'total_amount'              => $order->get_total(),
				'total_amount_formatted'    => $order->get_formatted_order_total(),
			)
		);
	}
}

if ( ! function_exists( 'rp_payparts_validate_amount' ) ) {
	/**
	 * Валідація суми для розстрочки – ОНОВЛЕНА ВЕРСІЯ
	 *
	 * @param float $amount Сума для перевірки
	 * @return array Результат валідації
	 */
	function rp_payparts_validate_amount( $amount ) {
		$gateway = rp_payparts_get_gateway();

		if ( ! $gateway ) {
			return array(
				'valid'   => false,
				'message' => __( 'Метод оплати недоступний', 'rp-payparts' ),
			);
		}

		// ✅ Отримуємо ліміти з реальних даних банків
		$limits = rp_payparts_get_banks_limits();

		// Перевірка мінімальної суми
		if ( $limits['min_amount'] > 0 && $amount < $limits['min_amount'] ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					__( 'Мінімальна сума для розстрочки: %s', 'rp-payparts' ),
					rp_payparts_format_currency( $limits['min_amount'] )
				),
			);
		}

		// Перевірка максимальної суми
		if ( $limits['max_amount'] > 0 && $amount > $limits['max_amount'] ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					__( 'Максимальна сума для розстрочки: %s', 'rp-payparts' ),
					rp_payparts_format_currency( $limits['max_amount'] )
				),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'Сума підходить для розстрочки', 'rp-payparts' ),
		);
	}
}

if ( ! function_exists( 'rp_payparts_get_banks_limits' ) ) {
	/**
	 * Отримати мінімальні та максимальні ліміти з усіх банків – НОВА ФУНКЦІЯ
	 *
	 * @return array
	 */
	function rp_payparts_get_banks_limits() {
		$banks = get_transient( 'rp_payparts_banks' );

		if ( false === $banks || ! is_array( $banks ) ) {
			// Резервні ліміти на основі реальних даних API
			return array(
				'min_amount' => 1,      // Monobank має мінімум 1 грн
				'max_amount' => 250000, // Більшість банків до 250 000
			);
		}

		$min_amount = PHP_INT_MAX;
		$max_amount = 0;

		foreach ( $banks as $bank ) {
			if ( isset( $bank['limits'] ) ) {
				if ( isset( $bank['limits']['min_amount'] ) ) {
					$min_amount = min( $min_amount, $bank['limits']['min_amount'] );
				}
				if ( isset( $bank['limits']['max_amount'] ) ) {
					$max_amount = max( $max_amount, $bank['limits']['max_amount'] );
				}
			}
		}

		return array(
			'min_amount' => $min_amount === PHP_INT_MAX ? 1 : $min_amount,
			'max_amount' => $max_amount ?: 250000,
		);
	}
}

if ( ! function_exists( 'rp_payparts_get_bank_fee' ) ) {
	/**
	 * Отримати комісію банку за певний період – НОВА ФУНКЦІЯ
	 *
	 * @param string $bank_slug Slug банку
	 * @param int    $period Період у місяцях
	 * @return float|null Комісія у відсотках або null, якщо не знайдено
	 */
	function rp_payparts_get_bank_fee( $bank_slug, $period ) {
		$banks = get_transient( 'rp_payparts_banks' );

		if ( false === $banks || ! is_array( $banks ) ) {
			return null;
		}

		foreach ( $banks as $bank ) {
			if ( isset( $bank['name'] ) && $bank['name'] === $bank_slug ) {
				if ( isset( $bank['periods'] ) && is_array( $bank['periods'] ) ) {
					foreach ( $bank['periods'] as $period_data ) {
						if ( isset( $period_data['period'] ) && intval( $period_data['period'] ) === $period ) {
							return isset( $period_data['fee'] ) ? floatval( $period_data['fee'] ) : null;
						}
					}
				}
				break;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'rp_payparts_cleanup_transients' ) ) {
	/**
	 * Очищення старих transient'ів
	 */
	function rp_payparts_cleanup_transients() {
		global $wpdb;

		// Очищаємо прострочені transient'и з нашим префіксом
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
             WHERE (option_name LIKE %s OR option_name LIKE %s)
             AND option_value < %d",
				'_transient_timeout_rp_payparts_%',
				'_transient_timeout_rozetkapay_payparts_%',
				time()
			)
		);

		// Очищаємо відповідні transient'и
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
				'_transient_rp_payparts_%',
				'_transient_rozetkapay_payparts_%'
			)
		);
	}
}

if ( ! function_exists( 'rp_payparts_debug' ) ) {
	/**
	 * Відлагоджувальне логування (лише в debug-режимі)
	 *
	 * @param mixed  $data Дані для лога
	 * @param string $context Контекст
	 */
	function rp_payparts_debug( $data, $context = 'debug' ) {
		if ( ! WP_DEBUG || ! WP_DEBUG_LOG ) {
			return;
		}

		$gateway = rp_payparts_get_gateway();
		if ( ! $gateway || ! $gateway->debug_mode ) {
			return;
		}

		$message = sprintf( '[%s] %s', $context, is_string( $data ) ? $data : print_r( $data, true ) );
		rp_payparts_log( $message, 'debug' );
	}
}

if ( ! function_exists( 'rp_payparts_get_plugin_data' ) ) {
	/**
	 * Отримати дані плагіна
	 *
	 * @return array
	 */
	function rp_payparts_get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = RP_PAYPARTS_PLUGIN_PATH . 'rozetkapay-payparts.php';

		if ( file_exists( $plugin_file ) ) {
			return get_plugin_data( $plugin_file );
		}

		return array(
			'Name'        => 'RozetkaPay Payparts',
			'Version'     => rp_payparts_get_plugin_version(),
			'Description' => __( 'Оплата частинами через RozetkaPay', 'rp-payparts' ),
			'Author'      => 'RozetkaPay',
		);
	}
}

if ( ! function_exists( 'rp_payparts_widget_shortcode' ) ) {
	/**
	 * Шорткод для віджета розстрочки
	 *
	 * @param array $atts Атрибути шорткоду
	 * @return string HTML віджета
	 */
	function rp_payparts_widget_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'price'      => 0,
				'style'      => 'compact',
				'show_logo'  => 'true',
				'show_terms' => 'true',
				'terms'      => '3,6,12',
			),
			$atts
		);

		$price = floatval( $atts['price'] );

		// Якщо ціну не вказано, пробуємо отримати з глобального продукту
		if ( $price <= 0 ) {
			global $product;
			if ( $product && $product->get_price() ) {
				$price = $product->get_price();
			}
		}

		if ( $price <= 0 ) {
			return '<p>' . esc_html__( 'Вкажіть ціну для розрахунку розстрочки', 'rp-payparts' ) . '</p>';
		}

		$gateway = rp_payparts_get_gateway();
		if ( ! $gateway || ! $gateway->is_available() ) {
			return '';
		}

		$validation = rp_payparts_validate_amount( $price );
		if ( ! $validation['valid'] ) {
			return '';
		}

		// Отримуємо доступні варіанти
		$banks = rp_payparts_get_available_banks();
		if ( empty( $banks ) ) {
			return '';
		}

		$target_terms = ! empty( $atts['terms'] ) ? array_map( 'intval', explode( ',', $atts['terms'] ) ) : array( 3, 6, 12 );
		$min_payment  = null;

		// Знаходимо мінімальний платіж
		foreach ( $target_terms as $term ) {
			$monthly_payment = rp_payparts_calculate_installment( $price, $term );
			if ( null === $min_payment || $monthly_payment < $min_payment ) {
				$min_payment = $monthly_payment;
			}
		}

		if ( null === $min_payment ) {
			return '';
		}

		ob_start();
		?>
		<div class="rozetkapay-payparts-widget rozetkapay-style-<?php echo esc_attr( $atts['style'] ); ?>">
			<?php if ( 'true' === $atts['show_logo'] ) : ?>
				<div class="rozetkapay-widget-logo">
					<img src="<?php echo esc_url( RP_PAYPARTS_PLUGIN_URL . 'assets/images/rozetkapay-logo.png' ); ?>" 
						alt="RozetkaPay" class="rozetkapay-logo">
				</div>
			<?php endif; ?>
			
			<div class="rozetkapay-widget-content">
				<div class="rozetkapay-widget-title">
					<?php esc_html_e( 'Розстрочка без переплат', 'rp-payparts' ); ?>
				</div>
				
				<div class="rozetkapay-widget-best">
					<?php esc_html_e( 'від', 'rp-payparts' ); ?>
					<strong><?php echo esc_html( rp_payparts_format_currency( $min_payment ) ); ?></strong>
					<?php esc_html_e( 'на місяць', 'rp-payparts' ); ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}

// Реєструємо шорткод тільки якщо він ще не зареєстрований
if ( ! shortcode_exists( 'rp_payparts_widget' ) ) {
	add_shortcode( 'rp_payparts_widget', 'rp_payparts_widget_shortcode' );
}

// === Added: reference helpers ===
if ( ! function_exists( 'rp_build_reference' ) ) {
	function rp_build_reference( WC_Order $order, $prefix = 'roz1', $sep = '-' ) {
		$number = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
		$prefix = trim( (string) $prefix );
		$sep    = (string) $sep;
		$ref    = $prefix !== '' ? $prefix . $sep . $number : (string) $number;
		$order->update_meta_data( '_rp_reference', $ref );
		$order->save();
		return $ref;
	}
}

if ( ! function_exists( 'rp_find_order_by_reference' ) ) {
	function rp_find_order_by_reference( $reference ) {
		$orders = wc_get_orders(
			array(
				'meta_key'   => '_rp_reference',
				'meta_value' => $reference,
				'limit'      => 1,
				'return'     => 'ids',
			)
		);
		return $orders ? wc_get_order( $orders[0] ) : false;
	}
}
// === End helpers ===
