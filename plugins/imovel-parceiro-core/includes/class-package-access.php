<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Imovel_Parceiro_Package_Access {

	const META_ALLOWED_ROLES = '_imovel_parceiro_allowed_roles';
	const META_CUSTOM_PLAN   = '_imovel_parceiro_custom_plan';
	const META_CUSTOM_USER   = '_imovel_parceiro_custom_user';
	const ROLE_AGENCY        = 'houzez_agency';
	const ROLE_AGENT         = 'houzez_agent';

	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'filter_package_queries' ) );
		add_action( 'template_redirect', array( $this, 'guard_payment_page' ) );
	}

	public static function selectable_roles() {
		return array(
			self::ROLE_AGENCY => __( 'Imobiliária', 'imovel-parceiro-core' ),
			self::ROLE_AGENT  => __( 'Corretor', 'imovel-parceiro-core' ),
		);
	}

	public static function allowed_roles( $package_id ) {
		$raw = get_post_meta( $package_id, self::META_ALLOWED_ROLES, true );

		if ( is_array( $raw ) ) {
			$roles = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$roles = explode( ',', $raw );
		} else {
			return array();
		}

		$roles = array_map( 'sanitize_key', array_map( 'trim', $roles ) );

		return array_values( array_intersect( $roles, array_keys( self::selectable_roles() ) ) );
	}

	public static function save_allowed_roles( $package_id, $roles ) {
		$valid = array_keys( self::selectable_roles() );
		$clean = array();

		foreach ( (array) $roles as $role ) {
			$role = sanitize_key( $role );
			if ( in_array( $role, $valid, true ) ) {
				$clean[] = $role;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		if ( empty( $clean ) ) {
			delete_post_meta( $package_id, self::META_ALLOWED_ROLES );
		} else {
			update_post_meta( $package_id, self::META_ALLOWED_ROLES, implode( ',', $clean ) );
		}
	}

	public static function target_role_for_user( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return null;
		}

		$roles = (array) $user->roles;

		if ( in_array( self::ROLE_AGENCY, $roles, true ) ) {
			return self::ROLE_AGENCY;
		}
		if ( in_array( self::ROLE_AGENT, $roles, true ) ) {
			return self::ROLE_AGENT;
		}

		return '';
	}

	public static function base_package_ids() {
		return get_posts(
			array(
				'post_type'                   => 'houzez_packages',
				'post_status'                 => 'publish',
				'posts_per_page'              => -1,
				'fields'                      => 'ids',
				'ipc_package_access_filtered' => 1,
				'meta_query'                  => array(
					'relation' => 'AND',
					array(
						'key'     => 'fave_package_visible',
						'value'   => 'yes',
						'compare' => '=',
					),
					array(
						'key'     => self::META_CUSTOM_PLAN,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	public static function package_ids_for_role( $role ) {
		$all     = self::base_package_ids();
		$allowed = array();

		foreach ( $all as $package_id ) {
			$roles = self::allowed_roles( $package_id );
			if ( empty( $roles ) || in_array( $role, $roles, true ) ) {
				$allowed[] = (int) $package_id;
			}
		}

		return $allowed;
	}

	public static function is_custom_plan( $package_id ) {
		return 'yes' === get_post_meta( $package_id, self::META_CUSTOM_PLAN, true );
	}

	public static function custom_user_ids( $package_id ) {
		$ids = get_post_meta( $package_id, self::META_CUSTOM_USER, false );

		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	public static function resolve_user_ids( $input ) {
		$parts = preg_split( '/[\s,;]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY );
		$ids   = array();

		foreach ( (array) $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( is_numeric( $part ) ) {
				$ids[] = (int) $part;
			} elseif ( is_email( $part ) ) {
				$user = get_user_by( 'email', $part );
				if ( $user ) {
					$ids[] = (int) $user->ID;
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public static function save_custom_plan( $package_id, $is_custom, $user_ids ) {
		if ( $is_custom ) {
			update_post_meta( $package_id, self::META_CUSTOM_PLAN, 'yes' );
		} else {
			delete_post_meta( $package_id, self::META_CUSTOM_PLAN );
		}

		delete_post_meta( $package_id, self::META_CUSTOM_USER );

		if ( $is_custom ) {
			foreach ( self::resolve_user_ids( is_array( $user_ids ) ? implode( ',', $user_ids ) : $user_ids ) as $uid ) {
				if ( $uid > 0 && get_userdata( $uid ) ) {
					add_post_meta( $package_id, self::META_CUSTOM_USER, $uid );
				}
			}
		}
	}

	public static function custom_package_ids_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'                   => 'houzez_packages',
				'post_status'                 => 'publish',
				'posts_per_page'              => -1,
				'fields'                      => 'ids',
				'ipc_package_access_filtered' => 1,
				'meta_query'                  => array(
					'relation' => 'AND',
					array(
						'key'     => 'fave_package_visible',
						'value'   => 'yes',
						'compare' => '=',
					),
					array(
						'key'     => self::META_CUSTOM_PLAN,
						'value'   => 'yes',
						'compare' => '=',
					),
					array(
						'key'     => self::META_CUSTOM_USER,
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			)
		);
	}

	/**
	 * @return array|null null = sem filtro (todos); array = apenas esses IDs.
	 */
	public static function visible_package_ids( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return null;
		}

		if ( ! $user_id ) {
			$ids = self::base_package_ids();
		} else {
			$ids  = array();
			$role = self::target_role_for_user( $user_id );

			if ( $role ) {
				$ids = self::package_ids_for_role( $role );
			}

			$ids = array_merge( $ids, self::custom_package_ids_for_user( $user_id ) );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );

		return $ids ? $ids : array( 0 );
	}

	public static function user_can_access( $package_id, $user_id = null ) {
		$ids = self::visible_package_ids( $user_id );

		if ( null === $ids ) {
			return true;
		}

		return in_array( (int) $package_id, array_map( 'intval', $ids ), true );
	}

	public function filter_package_queries( $query ) {
		if ( ! $query instanceof WP_Query || is_admin() || $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'ipc_package_access_filtered' ) ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$is_package = ( 'houzez_packages' === $post_type )
			|| ( is_array( $post_type ) && in_array( 'houzez_packages', $post_type, true ) );

		if ( ! $is_package ) {
			return;
		}

		$ids = self::visible_package_ids();
		if ( null === $ids ) {
			return;
		}

		$query->set( 'post__in', $ids );
		$query->set( 'ipc_package_access_filtered', 1 );
	}

	public function guard_payment_page() {
		if ( is_admin() || ! isset( $_GET['selected_package'] ) || ! is_user_logged_in() ) {
			return;
		}

		$package_id = absint( wp_unslash( $_GET['selected_package'] ) );
		if ( ! $package_id || 'houzez_packages' !== get_post_type( $package_id ) ) {
			return;
		}

		if ( ! self::user_can_access( $package_id ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}
}

new Imovel_Parceiro_Package_Access();
