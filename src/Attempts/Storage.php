<?php
declare( strict_types=1 );

namespace Lipe\Limit_Logins\Attempts;

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Settings;
use function Lipe\Limit_Logins\container;

/**
 * Logged failures, kept in their own option which is not autoloaded.
 *
 * Uses core's options API, which early drop can reach before CMB2 loads.
 *
 * @author Mat Lipe
 * @since  September 2026
 *
 * @phpstan-import-type DATA from Attempt
 */
final class Storage {
	public const string OPTION = 'lipe/limit-logins/attempts/storage/logged-failures';

	/**
	 * Most rows kept, so a distributed attack cannot grow the option without bound.
	 */
	public const int MAX_ROWS = 200;


	/**
	 * Get the stored failures, including expired rows not yet pruned.
	 *
	 * Incomplete rows are dropped or defaulted, so hand edited data
	 * never turns into warnings.
	 *
	 * @return list<Attempt>
	 */
	public function get(): array {
		$rows = get_option( self::OPTION, [] );

		return $this->to_attempts( \is_array( $rows ) ? $rows : [] );
	}


	/**
	 * Get the stored failures as rows for the settings screen.
	 *
	 * @phpstan-return list<DATA>
	 */
	public function get_rows(): array {
		return $this->to_rows( $this->get() );
	}


	/**
	 * Store the failures, pruned to the rows worth keeping.
	 *
	 * @param list<Attempt> $attempts - Every failure to keep.
	 * @param Attempt|null  $current  - Failure recorded by this request, which is never pruned.
	 */
	public function save( array $attempts, ?Attempt $current = null ): void {
		update_option( self::OPTION, $this->to_rows( $this->prune( $attempts, $current ) ), false );
	}


	/**
	 * Store failures from raw rows, such as those submitted on the settings screen.
	 *
	 * @param array<mixed> $rows - Rows in the shape of `Attempt::jsonSerialize()`.
	 */
	public function save_rows( array $rows ): void {
		$this->save( $this->to_attempts( $rows ) );
	}


	/**
	 * Are failures still stored within the autoloaded settings option?
	 *
	 * Drives the settings field which runs the migration.
	 */
	public function has_legacy(): bool {
		$settings = get_option( Settings::NAME, [] );

		return \is_array( $settings ) && \array_key_exists( Settings::LOGGED_FAILURES, $settings );
	}


	/**
	 * Move failures logged by versions which stored them within the
	 * autoloaded settings option.
	 *
	 * Rows already in the new option are kept.
	 *
	 * The legacy key leaves through CMB2's option object so its in-memory copy
	 * drops it as well. A plain `update_option` is written back over when the
	 * settings page finishes saving its fields.
	 */
	public function migrate(): void {
		$settings = get_option( Settings::NAME, [] );
		if ( \is_array( $settings ) && \array_key_exists( Settings::LOGGED_FAILURES, $settings ) ) {
			$current = get_option( self::OPTION, [] );
			$legacy = $settings[ Settings::LOGGED_FAILURES ];
			$this->save_rows( \array_merge( \is_array( $current ) ? $current : [], \is_array( $legacy ) ? $legacy : [] ) );

			cmb2_options( Settings::NAME )->remove( Settings::LOGGED_FAILURES, true );
		}
	}


	/**
	 * Drop expired rows, then keep up to `MAX_ROWS`.
	 *
	 * The current failure is kept first, blocks next, then the newest rows.
	 *
	 * @param list<Attempt> $attempts - Failures to prune.
	 * @param Attempt|null  $current  - Failure recorded by this request.
	 *
	 * @return list<Attempt>
	 */
	private function prune( array $attempts, ?Attempt $current ): array {
		$attempts = \array_values( \array_filter( $attempts, function( Attempt $attempt ): bool {
			return ! $attempt->is_expired();
		} ) );
		if ( \count( $attempts ) <= self::MAX_ROWS ) {
			return $attempts;
		}

		$ranked = $attempts;
		\usort( $ranked, function( Attempt $a, Attempt $b ) use ( $current ): int {
			return [ $b === $current, $b->is_blocked(), $b->expires ] <=> [ $a === $current, $a->is_blocked(), $a->expires ];
		} );
		$kept = \array_slice( $ranked, 0, self::MAX_ROWS );

		return \array_values( \array_filter( $attempts, function( Attempt $attempt ) use ( $kept ): bool {
			return \in_array( $attempt, $kept, true );
		} ) );
	}


	/**
	 * @param list<Attempt> $attempts - Failures to serialize.
	 *
	 * @phpstan-return list<DATA>
	 */
	private function to_rows( array $attempts ): array {
		return \array_map( function( Attempt $attempt ): array {
			return $attempt->jsonSerialize();
		}, $attempts );
	}


	/**
	 * @param array<mixed> $rows - Rows in the shape of `Attempt::jsonSerialize()`.
	 *
	 * @return list<Attempt>
	 */
	private function to_attempts( array $rows ): array {
		$rows = \array_filter( $rows, function( $row ): bool {
			if ( \is_array( $row ) && isset( $row[ Attempt::USERNAME ], $row[ Attempt::IP ] ) ) {
				return '' !== $row[ Attempt::USERNAME ] || '' !== $row[ Attempt::IP ];
			}
			return false;
		} );

		return \array_values( \array_map( function( array $row ): Attempt {
			return Attempt::factory( [
				Attempt::COUNT    => $row[ Attempt::COUNT ] ?? 1,
				Attempt::EXPIRES  => $row[ Attempt::EXPIRES ] ?? (int) \gmdate( 'U' ) + Attempts::DURATION,
				Attempt::GATEWAY  => $row[ Attempt::GATEWAY ] ?? Gateway::WP_LOGIN->value,
				Attempt::IP       => $row[ Attempt::IP ],
				Attempt::KEY      => $row[ Attempt::KEY ] ?? '',
				Attempt::USERNAME => $row[ Attempt::USERNAME ],
			] );
		}, $rows ) );
	}


	public static function in(): self {
		return container()->get( __CLASS__ );
	}
}
