<?php
/**
 * RozetkaPay Payparts Helper Functions - ВИПРАВЛЕНА ВЕРСІЯ
 * Допоміжні функції для плагіна
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rozetkapay_payparts_legacy_meta_key' ) ) {
	/**
	 * Назва meta-ключа, під якою значення зберігалося до версії 2.1.0.
	 *
	 * До 2.1.0 дані розстрочки мали префікс "_rp_payparts_", решта – "_rp_".
	 *
	 * @param string $key Поточний meta-ключ з префіксом "_rozetkapay_payparts_".
	 * @return string Застарілий ключ або порожній рядок, якщо його немає.
	 */
	function rozetkapay_payparts_legacy_meta_key( $key ) {
		$prefix = '_rozetkapay_payparts_';
		if ( 0 !== strpos( $key, $prefix ) ) {
			return '';
		}

		$name          = substr( $key, strlen( $prefix ) );
		$payparts_keys = array( 'option', 'selected_bank', 'external_id', 'bank', 'term', 'test_mode', 'test_redirect_url', 'payment_id', 'payment_url', 'transaction_id' );

		return in_array( $name, $payparts_keys, true ) ? '_rp_payparts_' . $name : '_rp_' . $name;
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_order_meta' ) ) {
	/**
	 * Отримати meta замовлення з урахуванням ключів, збережених до версії 2.1.0.
	 *
	 * @param WC_Abstract_Order|int $order Замовлення, повернення або їх ID.
	 * @param string                $key   Meta-ключ з префіксом "_rozetkapay_payparts_".
	 * @return mixed Значення або порожній рядок.
	 */
	function rozetkapay_payparts_get_order_meta( $order, $key ) {
		if ( ! $order instanceof WC_Abstract_Order ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order ) {
			return '';
		}

		$value = $order->get_meta( $key, true );
		if ( '' !== $value && null !== $value ) {
			return $value;
		}

		$legacy_key = rozetkapay_payparts_legacy_meta_key( $key );
		if ( '' === $legacy_key ) {
			return $value;
		}

		$value = $order->get_meta( $legacy_key, true );
		if ( '' === $value || null === $value ) {
			// До 2.1.0 частина ключів писалася напряму в post meta, яку таблиці HPOS не бачать.
			$value = get_post_meta( $order->get_id(), $legacy_key, true );
		}

		return $value;
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_orders_by_meta' ) ) {
	/**
	 * Знайти замовлення за meta-значенням з урахуванням ключів, збережених до версії 2.1.0.
	 *
	 * @param string $key   Meta-ключ з префіксом "_rozetkapay_payparts_".
	 * @param mixed  $value Значення для пошуку.
	 * @param array  $args  Додаткові аргументи wc_get_orders().
	 * @return array
	 */
	function rozetkapay_payparts_get_orders_by_meta( $key, $value, $args = array() ) {
		$orders = wc_get_orders(
			array_merge(
				$args,
				array(
					'meta_key'   => $key,
					'meta_value' => $value,
				)
			)
		);

		$legacy_key = rozetkapay_payparts_legacy_meta_key( $key );
		if ( empty( $orders ) && '' !== $legacy_key ) {
			$orders = wc_get_orders(
				array_merge(
					$args,
					array(
						'meta_key'   => $legacy_key,
						'meta_value' => $value,
					)
				)
			);
		}

		return $orders;
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_callback_signature' ) ) {
	/**
	 * Отримати підпис callback із заголовків запиту.
	 *
	 * @return string Підпис або порожній рядок.
	 */
	function rozetkapay_payparts_get_callback_signature() {
		$headers = array( 'HTTP_X_ROZETKAPAY_SIGNATURE', 'HTTP_X_SIGNATURE', 'HTTP_SIGNATURE' );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			}
		}

		return '';
	}
}

if ( ! function_exists( 'rozetkapay_payparts_verify_callback_signature' ) ) {
	/**
	 * Перевірити підпис callback від RozetkaPay.
	 *
	 * Формула: base64url( sha1( password + base64url( body ) + password ) ).
	 * RozetkaPay кодує тіло з padding "=", варіант без padding приймається для сумісності.
	 *
	 * @param string $payload   Сире тіло запиту.
	 * @param string $signature Підпис із заголовка X-ROZETKAPAY-SIGNATURE.
	 * @param string $password  API-пароль мерчанта.
	 * @return bool
	 */
	function rozetkapay_payparts_verify_callback_signature( $payload, $signature, $password ) {
		if ( '' === (string) $payload || '' === (string) $signature || '' === (string) $password ) {
			return false;
		}

		$signature = str_replace( array( 'sha1=', 'SHA1=', 'signature=' ), '', trim( $signature ) );
		$signature = rtrim( strtr( $signature, '+/', '-_' ), '=' );

		$encoded_body = strtr( base64_encode( $payload ), '+/', '-_' );

		foreach ( array_unique( array( $encoded_body, rtrim( $encoded_body, '=' ) ) ) as $body ) {
			$expected = rtrim( strtr( base64_encode( sha1( $password . $body . $password, true ) ), '+/', '-_' ), '=' );
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_month_declension' ) ) {
	/**
	 * Отримати правильне відмінювання слова "місяць"
	 *
	 * @param int $number Кількість місяців
	 * @return string Правильне відмінювання
	 */
	function rozetkapay_payparts_get_month_declension( $number ) {
		$cases  = array( 2, 0, 1, 1, 1, 2 );
		$titles = array(
			__( 'місяць', 'rozetkapay-payparts' ),
			__( 'місяці', 'rozetkapay-payparts' ),
			__( 'місяців', 'rozetkapay-payparts' ),
		);

		return $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_bank_name' ) ) {
	/**
	 * Отримати зручну для користувача назву банку за slug
	 *
	 * @param string $bank_slug Slug банку
	 * @return string Назва банку
	 */
	function rozetkapay_payparts_get_bank_name( $bank_slug ) {
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

if ( ! function_exists( 'rozetkapay_payparts_get_bank_min_period' ) ) {
	/**
	 * Отримати мінімальний період для банку – НОВА ФУНКЦІЯ
	 *
	 * @param string $bank_slug Slug банку
	 * @return int Мінімальний період у місяцях
	 */
	function rozetkapay_payparts_get_bank_min_period( $bank_slug ) {
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

if ( ! function_exists( 'rozetkapay_payparts_validate_bank_terms' ) ) {
	/**
	 * Валідація списку періодів для банку – НОВА ФУНКЦІЯ
	 *
	 * @param string $bank_slug Slug банку
	 * @param array  $periods Масив періодів
	 * @return array Відфільтрований масив періодів
	 */
	function rozetkapay_payparts_validate_bank_terms( $bank_slug, $periods ) {
		$min_period = rozetkapay_payparts_get_bank_min_period( $bank_slug );

		return array_filter(
			$periods,
			function ( $period ) use ( $min_period ) {
				return intval( $period ) >= $min_period && intval( $period ) <= 25;
			}
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_format_currency' ) ) {
	/**
	 * Форматування валюти
	 *
	 * @param float  $amount Сума
	 * @param string $currency Код валюти
	 * @return string Відформатована сума
	 */
	function rozetkapay_payparts_format_currency( $amount, $currency = 'UAH' ) {
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

if ( ! function_exists( 'rozetkapay_payparts_is_plugin_active' ) ) {
	/**
	 * Перевірити, чи активний плагін
	 *
	 * @return bool
	 */
	function rozetkapay_payparts_is_plugin_active() {
		return class_exists( 'RozetkaPay_Payparts_Gateway' );
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_gateway' ) ) {
	/**
	 * Отримати екземпляр gateway
	 *
	 * @return RozetkaPay_Payparts_Gateway|null
	 */
	function rozetkapay_payparts_get_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
			return null;
		}

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		return isset( $gateways['rp_payparts'] ) ? $gateways['rp_payparts'] : null;
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_payment_method_type' ) ) {
	/**
	 * Визначити тип методу оплати за замовленням – НОВА ФУНКЦІЯ
	 *
	 * @param WC_Order $order Замовлення
	 * @return string|null Тип методу ('payparts' або 'standard')
	 */
	function rozetkapay_payparts_get_payment_method_type( $order ) {
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

if ( ! function_exists( 'rozetkapay_payparts_get_payment_method_title' ) ) {
	/**
	 * Отримати назву методу оплати – НОВА ФУНКЦІЯ
	 *
	 * @param string $method Тип методу ('payparts' або 'standard')
	 * @return string
	 */
	function rozetkapay_payparts_get_payment_method_title( $method ) {
		switch ( $method ) {
			case 'payparts':
				return __( 'RozetkaPay Оплата частинами', 'rozetkapay-payparts' );
			case 'standard':
				return __( 'RozetkaPay Банківські картки', 'rozetkapay-payparts' );
			default:
				return __( 'RozetkaPay', 'rozetkapay-payparts' );
		}
	}
}

if ( ! function_exists( 'rozetkapay_payparts_calculate_installment' ) ) {
	/**
	 * Розрахувати розмір щомісячного платежу
	 *
	 * @param float $total Загальна сума
	 * @param int   $months Кількість місяців
	 * @return float Розмір щомісячного платежу
	 */
	function rozetkapay_payparts_calculate_installment( $total, $months ) {
		if ( $months <= 0 ) {
			return 0;
		}

		return round( $total / $months, 2 );
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_available_banks' ) ) {
	/**
	 * Отримати список доступних банків
	 *
	 * @return array
	 */
	function rozetkapay_payparts_get_available_banks() {
		$gateway = rozetkapay_payparts_get_gateway();

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
		$banks = get_transient( 'rozetkapay_payparts_banks' );
		if ( false === $banks && method_exists( $gateway, 'get_api_client' ) ) {
			$api   = $gateway->get_api_client();
			$banks = $api->fetch_banks();

			if ( ! empty( $banks ) && ! is_wp_error( $banks ) ) {
				set_transient( 'rozetkapay_payparts_banks', $banks, HOUR_IN_SECONDS );
			}
		}

		return is_array( $banks ) ? $banks : array();
	}
}

if ( ! function_exists( 'rozetkapay_payparts_clear_cache' ) ) {
	/**
	 * Очистити кеш плагіна
	 */
	function rozetkapay_payparts_clear_cache() {
		delete_transient( 'rozetkapay_payparts_banks' );
		delete_transient( 'rozetkapay_payparts_banks' ); // Старий ключ для сумісності

		rozetkapay_payparts_log( 'Cache cleared' );
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_plugin_version' ) ) {
	/**
	 * Отримати версію плагіна
	 *
	 * @return string
	 */
	function rozetkapay_payparts_get_plugin_version() {
		return defined( 'ROZETKAPAY_PAYPARTS_VERSION' ) ? ROZETKAPAY_PAYPARTS_VERSION : '2.0.2';
	}
}

if ( ! function_exists( 'rozetkapay_payparts_is_woocommerce_active' ) ) {
	/**
	 * Перевірити, чи активний WooCommerce
	 *
	 * @return bool
	 */
	function rozetkapay_payparts_is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_order_payment_data' ) ) {
	/**
	 * Отримати метадані замовлення, пов’язані з розстрочкою
	 *
	 * @param int $order_id ID замовлення
	 * @return array
	 */
	function rozetkapay_payparts_get_order_payment_data( $order_id ) {
		return array(
			'option'         => rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_option' ),
			'payment_id'     => rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_payment_id' ),
			'payment_url'    => rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_payment_url' ),
			'transaction_id' => rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_transaction_id' ),
			'bank'           => rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_bank' ),
			'term'           => rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_term' ),
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_parse_option' ) ) {
	/**
	 * Парсинг опції розстрочки – ОНОВЛЕНА ВЕРСІЯ
	 *
	 * @param string $option Опція у форматі "bank|term"
	 * @return array|null
	 */
	function rozetkapay_payparts_parse_option( $option ) {
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
		$bank_name = rozetkapay_payparts_get_bank_name( $bank_slug );

		return array(
			'bank_slug' => $bank_slug,
			'bank_name' => $bank_name,
			'term'      => $term,
			'term_text' => $term . ' ' . rozetkapay_payparts_get_month_declension( $term ),
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_order_installment_summary' ) ) {
	/**
	 * Отримати зведення по розстрочці для замовлення
	 *
	 * @param int $order_id ID замовлення
	 * @return array|null
	 */
	function rozetkapay_payparts_get_order_installment_summary( $order_id ) {
		$option = rozetkapay_payparts_get_order_meta( $order_id, '_rozetkapay_payparts_option' );
		$parsed = rozetkapay_payparts_parse_option( $option );

		if ( ! $parsed ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$monthly_payment = rozetkapay_payparts_calculate_installment( $order->get_total(), $parsed['term'] );

		return array_merge(
			$parsed,
			array(
				'monthly_payment'           => $monthly_payment,
				'monthly_payment_formatted' => rozetkapay_payparts_format_currency( $monthly_payment ),
				'total_amount'              => $order->get_total(),
				'total_amount_formatted'    => $order->get_formatted_order_total(),
			)
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_validate_amount' ) ) {
	/**
	 * Валідація суми для розстрочки – ОНОВЛЕНА ВЕРСІЯ
	 *
	 * @param float $amount Сума для перевірки
	 * @return array Результат валідації
	 */
	function rozetkapay_payparts_validate_amount( $amount ) {
		$gateway = rozetkapay_payparts_get_gateway();

		if ( ! $gateway ) {
			return array(
				'valid'   => false,
				'message' => __( 'Метод оплати недоступний', 'rozetkapay-payparts' ),
			);
		}

		// ✅ Отримуємо ліміти з реальних даних банків
		$limits = rozetkapay_payparts_get_banks_limits();

		// Перевірка мінімальної суми
		if ( $limits['min_amount'] > 0 && $amount < $limits['min_amount'] ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					// translators: %s: formatted minimum order amount.
					__( 'Мінімальна сума для розстрочки: %s', 'rozetkapay-payparts' ),
					rozetkapay_payparts_format_currency( $limits['min_amount'] )
				),
			);
		}

		// Перевірка максимальної суми
		if ( $limits['max_amount'] > 0 && $amount > $limits['max_amount'] ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					// translators: %s: formatted maximum order amount.
					__( 'Максимальна сума для розстрочки: %s', 'rozetkapay-payparts' ),
					rozetkapay_payparts_format_currency( $limits['max_amount'] )
				),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'Сума підходить для розстрочки', 'rozetkapay-payparts' ),
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_banks_limits' ) ) {
	/**
	 * Отримати мінімальні та максимальні ліміти з усіх банків – НОВА ФУНКЦІЯ
	 *
	 * @return array
	 */
	function rozetkapay_payparts_get_banks_limits() {
		$banks = get_transient( 'rozetkapay_payparts_banks' );

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

if ( ! function_exists( 'rozetkapay_payparts_get_bank_fee' ) ) {
	/**
	 * Отримати комісію банку за певний період – НОВА ФУНКЦІЯ
	 *
	 * @param string $bank_slug Slug банку
	 * @param int    $period Період у місяцях
	 * @return float|null Комісія у відсотках або null, якщо не знайдено
	 */
	function rozetkapay_payparts_get_bank_fee( $bank_slug, $period ) {
		$banks = get_transient( 'rozetkapay_payparts_banks' );

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

if ( ! function_exists( 'rozetkapay_payparts_cleanup_transients' ) ) {
	/**
	 * Очищення старих transient'ів
	 */
	function rozetkapay_payparts_cleanup_transients() {
		global $wpdb;

		// Очищаємо прострочені transient'и з нашим префіксом
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
             WHERE (option_name LIKE %s OR option_name LIKE %s)
             AND option_value < %d",
				'_transient_timeout_rozetkapay_payparts_%',
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
				'_transient_rozetkapay_payparts_%',
				'_transient_rozetkapay_payparts_%'
			)
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_debug' ) ) {
	/**
	 * Відлагоджувальне логування (лише в debug-режимі)
	 *
	 * @param mixed  $data Дані для лога
	 * @param string $context Контекст
	 */
	function rozetkapay_payparts_debug( $data, $context = 'debug' ) {
		if ( ! WP_DEBUG || ! WP_DEBUG_LOG ) {
			return;
		}

		$gateway = rozetkapay_payparts_get_gateway();
		if ( ! $gateway || ! $gateway->debug_mode ) {
			return;
		}

		$message = sprintf( '[%s] %s', $context, is_string( $data ) ? $data : wp_json_encode( $data ) );
		rozetkapay_payparts_log( $message, 'debug' );
	}
}

if ( ! function_exists( 'rozetkapay_payparts_get_plugin_data' ) ) {
	/**
	 * Отримати дані плагіна
	 *
	 * @return array
	 */
	function rozetkapay_payparts_get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = ROZETKAPAY_PAYPARTS_PLUGIN_PATH . 'rozetkapay-payparts.php';

		if ( file_exists( $plugin_file ) ) {
			return get_plugin_data( $plugin_file );
		}

		return array(
			'Name'        => 'RozetkaPay Payparts',
			'Version'     => rozetkapay_payparts_get_plugin_version(),
			'Description' => __( 'Оплата частинами через RozetkaPay', 'rozetkapay-payparts' ),
			'Author'      => 'RozetkaPay',
		);
	}
}

if ( ! function_exists( 'rozetkapay_payparts_widget_shortcode' ) ) {
	/**
	 * Шорткод для віджета розстрочки
	 *
	 * @param array $atts Атрибути шорткоду
	 * @return string HTML віджета
	 */
	function rozetkapay_payparts_widget_shortcode( $atts ) {
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
			return '<p>' . esc_html__( 'Вкажіть ціну для розрахунку розстрочки', 'rozetkapay-payparts' ) . '</p>';
		}

		$gateway = rozetkapay_payparts_get_gateway();
		if ( ! $gateway || ! $gateway->is_available() ) {
			return '';
		}

		$validation = rozetkapay_payparts_validate_amount( $price );
		if ( ! $validation['valid'] ) {
			return '';
		}

		// Отримуємо доступні варіанти
		$banks = rozetkapay_payparts_get_available_banks();
		if ( empty( $banks ) ) {
			return '';
		}

		$target_terms = ! empty( $atts['terms'] ) ? array_map( 'intval', explode( ',', $atts['terms'] ) ) : array( 3, 6, 12 );
		$min_payment  = null;

		// Знаходимо мінімальний платіж
		foreach ( $target_terms as $term ) {
			$monthly_payment = rozetkapay_payparts_calculate_installment( $price, $term );
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
					<img src="<?php echo esc_url( ROZETKAPAY_PAYPARTS_PLUGIN_URL . 'assets/images/rozetkapay-logo.png' ); ?>" 
						alt="RozetkaPay" class="rozetkapay-logo">
				</div>
			<?php endif; ?>
			
			<div class="rozetkapay-widget-content">
				<div class="rozetkapay-widget-title">
					<?php esc_html_e( 'Розстрочка без переплат', 'rozetkapay-payparts' ); ?>
				</div>
				
				<div class="rozetkapay-widget-best">
					<?php esc_html_e( 'від', 'rozetkapay-payparts' ); ?>
					<strong><?php echo esc_html( rozetkapay_payparts_format_currency( $min_payment ) ); ?></strong>
					<?php esc_html_e( 'на місяць', 'rozetkapay-payparts' ); ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}

// Реєструємо шорткод тільки якщо він ще не зареєстрований
if ( ! shortcode_exists( 'rozetkapay_payparts_widget' ) ) {
	add_shortcode( 'rozetkapay_payparts_widget', 'rozetkapay_payparts_widget_shortcode' );
}

// Назва шорткоду до версії 2.1.0. Залишено для сторінок, на яких його вже вставлено.
if ( ! shortcode_exists( 'rp_payparts_widget' ) ) {
	add_shortcode( 'rp_payparts_widget', 'rozetkapay_payparts_widget_shortcode' );
}

// === Added: reference helpers ===
if ( ! function_exists( 'rozetkapay_payparts_build_reference' ) ) {
	function rozetkapay_payparts_build_reference( WC_Order $order, $prefix = 'roz1', $sep = '-' ) {
		$number = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order->get_id();
		$prefix = trim( (string) $prefix );
		$sep    = (string) $sep;
		$ref    = $prefix !== '' ? $prefix . $sep . $number : (string) $number;
		$order->update_meta_data( '_rozetkapay_payparts_reference', $ref );
		$order->save();
		return $ref;
	}
}

if ( ! function_exists( 'rozetkapay_payparts_find_order_by_reference' ) ) {
	function rozetkapay_payparts_find_order_by_reference( $reference ) {
		$orders = rozetkapay_payparts_get_orders_by_meta(
			'_rozetkapay_payparts_reference',
			$reference,
			array(
				'limit'  => 1,
				'return' => 'ids',
			)
		);
		return $orders ? wc_get_order( $orders[0] ) : false;
	}
}
// === End helpers ===
