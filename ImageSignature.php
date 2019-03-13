<?php

class ImageSignature {

	public static function imageToColors( $path, $grayscale = false ) {
		$image = imagecreatefromjpeg( $path );
		$width = imagesx($image);
		$height = imagesy($image);
		$colors = array();

		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$rgb = imagecolorat($image, $x, $y);
				$rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));

				$colors[$y][$x] = $rgba;
				if ( $grayscale ) {
					$colors[$y][$x] = (0.2125 * $rgba['red'] + 0.7154 * $rgba['green'] + 0.0721 * $rgba['blue']) / 255;
				}
			}
		}

		return $colors;
	}

	public static function cropImage( $image, $lower_percentile = 5, $upper_percentile = 95, $fix_ratio = false ) {
		$shape = Matrix::shape( $image );
		$rw = Matrix::cumsum(Matrix::sum(Matrix::abs(Matrix::diff($image, 1)),1));
		$cw = Matrix::cumsum(Matrix::sum(Matrix::abs(Matrix::diff($image, 0)),0));

		$upper_col_limit = Matrix::percentile_index($cw, $upper_percentile, 'left');
		$lower_col_limit = Matrix::percentile_index($cw, $lower_percentile, 'right');

		$upper_row_limit = Matrix::percentile_index($rw, $upper_percentile, 'left');
		$lower_row_limit = Matrix::percentile_index($rw, $lower_percentile, 'right');

		// if image is nearly featureless, use default region
		if ( $lower_row_limit > $upper_row_limit ) {
			$lower_row_limit = round($lower_percentile/100 * $shape[0]);
			$upper_row_limit = round($upper_percentile/100 * $shape[0]);
		}

		if ( $lower_col_limit > $upper_col_limit ) {
			$lower_col_limit = round($lower_percentile/100 * $shape[1]);
			$upper_col_limit = round($upper_percentile/100 * $shape[1]);
		}

		if ( $fix_ratio ) {
			if ( ( $upper_row_limit - $lower_row_limit ) > ( $upper_col_limit - $lower_col_limit ) ) {
				return [
					[$lower_row_limit, $upper_row_limit],
					[$lower_row_limit, $upper_row_limit]
				];
			} else {
				return [
					[$lower_col_limit, $upper_col_limit],
					[$lower_col_limit, $upper_col_limit]
				];
			}
		}

		// otherwise, proceed as normal
		return [
			[$lower_row_limit, $upper_row_limit],
			[$lower_col_limit, $upper_col_limit]
		];
	}

	public static function computeGridPoints($image, $n = 9, $window = false) {
		$shape = Matrix::shape( $image );
		if ( !$window ) {
			$window = [[0, $shape[0]],[0, $shape[1]]];
		}

		$x_coords = Matrix::linspace($window[0][0],$window[0][1], $n+2);
		$y_coords = Matrix::linspace($window[1][0],$window[1][1], $n+2);

		return [Matrix::slice($x_coords,[1,-1]), Matrix::slice($y_coords,[1,-1])];
	}

	public static function computeMeanLevel($image, $coords, $P = false) {
		$shape = Matrix::shape( $image );
		if ( !$P ) {
			$P = max([2.0, (int)(0.5 + min($shape)/20)]);
		}

		$x_coords = $coords[0];
		$y_coords = $coords[1];

		$avg_grey = Matrix::zeros([count($x_coords), count($y_coords)]);

		foreach ($x_coords as $i => $x) {
			$lower_x_lim = (int) max([$x - $P/2, 0]);
			$upper_x_lim = (int) min([$lower_x_lim + $P, $shape[0]]);

			foreach ($y_coords as $j => $y) {
				$lower_y_lim = (int) max([$y - $P/2, 0]);
				$upper_y_lim = (int) min([$lower_y_lim + $P, $shape[1]]);

				$grid = Matrix::slice( $image, [$lower_x_lim, $upper_x_lim],[$lower_y_lim, $upper_y_lim]);
				$grid_shape = Matrix::shape($grid);
				$avg_grey[$i][$j] = array_sum(Matrix::sum($grid))/($grid_shape[0]*$grid_shape[1]);
			}
		}

		return $avg_grey;
	}

	public static function computeDifferentials($grey_matrix, $diagonal_neighbors = true) {
		$shape = Matrix::shape($grey_matrix);
		$diff = Matrix::diff($grey_matrix);
		$zeros = Matrix::zeros([$shape[0],1]);

		$right_neighbors = Matrix::multiply(Matrix::concatenate($diff, $zeros), -1);

		$left_neighbors  = Matrix::multiply(Matrix::concatenate(Matrix::slice( $right_neighbors, [0],[-1]), Matrix::slice( $right_neighbors, [0],[0, -1])), -1);

		$diff = Matrix::diff($grey_matrix, 0);
		$zeros = Matrix::zeros([1,$shape[0]]);

		$down_neighbors = Matrix::multiply(Matrix::concatenate($diff, $zeros, 0), -1);
		$up_neighbors   = Matrix::multiply(Matrix::concatenate(Matrix::slice($down_neighbors, [-1]), Matrix::slice($down_neighbors, [0, -1]), 0), -1);

		if($diagonal_neighbors) {

			list($upper_left_neighbors, $lower_right_neighbors) = self::computeDiagonalNeighbors($grey_matrix);

			$flipped = Matrix::fliplr($grey_matrix);

			list($upper_right_neighbors, $lower_left_neighbors) = self::computeDiagonalNeighbors($flipped);

			return Matrix::dstack([
				$upper_left_neighbors,
				$up_neighbors,
				Matrix::fliplr($upper_right_neighbors),
				$left_neighbors,
				$right_neighbors,
				Matrix::fliplr($lower_left_neighbors),
				$down_neighbors,
				$lower_right_neighbors
			]);
		}

		return Matrix::dstack([
			$up_neighbors,
			$left_neighbors,
			$right_neighbors,
			$down_neighbors
		]);
	}

	public static function computeDiagonalNeighbors($grey_matrix) {
		$shape = Matrix::shape($grey_matrix);
		$diagonals = range(-$shape[0] + 1, $shape[0] - 1);

		$diagflat = [];

		foreach($diagonals as $i) {
			$diag_diff = Matrix::diff(Matrix::diag($grey_matrix, $i));
			array_splice($diag_diff, 0, 0, 0);
			$diagflat[] = Matrix::diagflat($diag_diff, $i);
		}

		$upper_neighbors = Matrix::sum($diagflat, 0);
		$slice = Matrix::slice($upper_neighbors, [1], [1]);
		$slice_shape = Matrix::shape($slice);

		$slice = Matrix::concatenate($slice, Matrix::zeros([1, $slice_shape[0]]), 0);
		$lower_neighbors = Matrix::concatenate($slice, Matrix::zeros([$slice_shape[0]+1, 1]));
		$lower_neighbors = Matrix::multiply($lower_neighbors, -1);

		return [$upper_neighbors, $lower_neighbors];
	}

	public static function normalizeAndThreshold($diff_array, $identital_tolerance=2/255.0, $n_levels=2) {
		$masked = Matrix::map($diff_array, function($v) use ($identital_tolerance) {
			return abs($v) < $identital_tolerance ? 0 : $v;
		});

		$positive = Matrix::flatten(Matrix::filter($masked, function($v) { return $v > 0; }));
		$negative = Matrix::flatten(Matrix::filter($masked, function($v) { return $v < 0; }));

		$positive_cutoffs = Matrix::percentile($positive, Matrix::linspace(0, 100, $n_levels+1));
		$negative_cutoffs = Matrix::percentile($negative, Matrix::linspace(100, 0, $n_levels+1));

		$enumerate = array_map(function($i) use($positive_cutoffs) { return array_slice($positive_cutoffs, $i, $i+2); }, range(0, count($positive_cutoffs)-2));

		foreach($enumerate as $level => $interval) {
			$masked =  Matrix::map($masked, function($v) use ($level, $interval) {
				if($v >= $interval[0] && $v <= $interval[1]) {
					return $level + 1;
				}
				return $v;
			});
		}

		$enumerate = array_map(function($i) use($negative_cutoffs) { return array_slice($negative_cutoffs, $i, $i+2); }, range(0, count($negative_cutoffs)-2));

		foreach($enumerate as $level => $interval) {
			$masked =  Matrix::map($masked, function($v) use ($level, $interval) {
				if($v <= $interval[0] && $v >= $interval[1]) {
					return -($level + 1);
				}

				return $v;
			});
		}

		return $masked;
	}

	public static function generateSignature($path) {
		# Step 1:    Load image as array of grey-levels
		$image = self::imageToColors($path, true);

		# Step 2a:   Determine cropping boundaries
		$image_limits = self::cropImage($image);

		# Step 2b:   Generate grid centers
		$coords = self::computeGridPoints($image, 9, $image_limits);

		# Step 3:    Compute grey level mean of each P x P
		#           square centered at each grid point
		$avg_grey = self::computeMeanLevel($image, $coords);

		# Step 4a:   Compute array of differences for each
		#           grid point vis-a-vis each neighbor
		$diff_matrix = self::computeDifferentials($avg_grey);

		# Step 4b: Bin differences to only 2n+1 values
		$normal_matrix = self::normalizeAndThreshold($diff_matrix);

		# Step 5: Flatten array and return signature
		return array_map('intval', Matrix::flatten($normal_matrix));
	}
}