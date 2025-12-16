<?php

namespace Alebarda\RankedPoll\Voting;

/**
 * Schulze Method Calculator for Ranked-Choice Voting
 *
 * Implements the Schulze method (also known as Beatpath method or Schwartz Sequential Dropping)
 * for determining the Condorcet winner in ranked-choice elections.
 *
 * Algorithm complexity: O(n³) where n = number of candidates
 * Suitable for polls with up to 50-100 options
 *
 * @see https://en.wikipedia.org/wiki/Schulze_method
 */
class SchulzeCalculator
{
	/**
	 * Calculate Schulze winner and strongest paths
	 *
	 * @param array $rankedVotes Format: [user_id => [response_id => rank_position]]
	 * @param array $allResponseIds All valid response IDs in the poll
	 * @return array ['winner' => int|null, 'strongestPaths' => array, 'preferences' => array, 'ranking' => array]
	 */
	public function calculate(array $rankedVotes, array $allResponseIds)
	{
		if (empty($allResponseIds))
		{
			return [
				'winner' => null,
				'strongestPaths' => [],
				'preferences' => [],
				'ranking' => []
			];
		}

		// Step 1: Build pairwise preference matrix
		$preferences = $this->buildPreferenceMatrix($rankedVotes, $allResponseIds);

		// Step 2: Calculate strongest paths using Floyd-Warshall
		$strongestPaths = $this->calculateStrongestPaths($preferences, $allResponseIds);

		// Step 3: Determine winner (Condorcet winner)
		$winner = $this->determineWinner($strongestPaths, $allResponseIds);

		// Step 4: Calculate full ranking of all options
		$ranking = $this->calculateFullRanking($strongestPaths, $allResponseIds);

		return [
			'winner' => $winner,
			'strongestPaths' => $strongestPaths,
			'preferences' => $preferences,
			'ranking' => $ranking
		];
	}

	/**
	 * Build pairwise preference matrix
	 *
	 * For each pair of candidates (A, B), count how many voters prefer A over B.
	 * Handles partial rankings: unranked options treated as tied for last place.
	 *
	 * @param array $rankedVotes [user_id => [response_id => rank_position]]
	 * @param array $allResponseIds All response IDs
	 * @return array $matrix[$i][$j] = number of voters who prefer option i over option j
	 */
	protected function buildPreferenceMatrix(array $rankedVotes, array $allResponseIds)
	{
		$matrix = [];

		// Initialize matrix with zeros
		foreach ($allResponseIds as $i)
		{
			foreach ($allResponseIds as $j)
			{
				$matrix[$i][$j] = 0;
			}
		}

		// Process each voter's rankings
		foreach ($rankedVotes as $userId => $rankings)
		{
			$ranked = array_keys($rankings);
			$unranked = array_diff($allResponseIds, $ranked);

			// Compare all ranked pairs
			foreach ($rankings as $responseA => $rankA)
			{
				foreach ($rankings as $responseB => $rankB)
				{
					// Lower rank number = higher preference
					if ($rankA < $rankB)
					{
						$matrix[$responseA][$responseB]++;
					}
				}
			}

			// Ranked options always beat unranked options
			foreach ($ranked as $responseA)
			{
				foreach ($unranked as $responseB)
				{
					$matrix[$responseA][$responseB]++;
				}
			}

			// Unranked vs Unranked: no preference (tie)
			// No matrix increment needed
		}

		return $matrix;
	}

	/**
	 * Calculate strongest paths using Floyd-Warshall algorithm
	 *
	 * The strength of a path from A to C through B is the minimum of:
	 * - strength from A to B
	 * - strength from B to C
	 *
	 * The strongest path is the maximum over all possible paths.
	 *
	 * @param array $preferences Pairwise preference matrix
	 * @param array $allResponseIds All response IDs
	 * @return array $p[$i][$j] = strength of strongest path from i to j
	 */
	protected function calculateStrongestPaths(array $preferences, array $allResponseIds)
	{
		$p = [];

		// Initialize strongest paths
		// If more voters prefer i over j, there's a direct path
		foreach ($allResponseIds as $i)
		{
			foreach ($allResponseIds as $j)
			{
				if ($i != $j)
				{
					if ($preferences[$i][$j] > $preferences[$j][$i])
					{
						$p[$i][$j] = $preferences[$i][$j];
					}
					else
					{
						$p[$i][$j] = 0;
					}
				}
			}
		}

		// Floyd-Warshall: find strongest paths through all intermediates
		foreach ($allResponseIds as $k)
		{
			foreach ($allResponseIds as $i)
			{
				if ($i == $k) continue;

				foreach ($allResponseIds as $j)
				{
					if ($i == $j || $j == $k) continue;

					// The strength of path i→k→j is min(p[i][k], p[k][j])
					// Update p[i][j] if this path is stronger
					$p[$i][$j] = max(
						$p[$i][$j],
						min($p[$i][$k], $p[$k][$j])
					);
				}
			}
		}

		return $p;
	}

	/**
	 * Determine Condorcet winner
	 *
	 * A candidate is the Schulze winner if for every other candidate,
	 * the strongest path to that candidate is stronger than the strongest
	 * path back.
	 *
	 * @param array $strongestPaths Strongest path matrix
	 * @param array $allResponseIds All response IDs
	 * @return int|null Response ID of winner, or null if tie
	 */
	protected function determineWinner(array $strongestPaths, array $allResponseIds)
	{
		$winners = [];

		foreach ($allResponseIds as $i)
		{
			$isWinner = true;

			foreach ($allResponseIds as $j)
			{
				if ($i == $j) continue;

				// i is not a winner if there exists j where p[j][i] >= p[i][j]
				if ($strongestPaths[$j][$i] >= $strongestPaths[$i][$j])
				{
					$isWinner = false;
					break;
				}
			}

			if ($isWinner)
			{
				$winners[] = $i;
			}
		}

		// Return single winner or null if tie
		return count($winners) === 1 ? $winners[0] : null;
	}

	/**
	 * Calculate full ranking of all options using Schulze method
	 *
	 * Iteratively finds winners among remaining candidates, removing them
	 * each round. Ties are assigned the same rank.
	 *
	 * @param array $strongestPaths Strongest path matrix
	 * @param array $allResponseIds All response IDs
	 * @return array $ranking[response_id] = rank_position (1 = first place)
	 */
	protected function calculateFullRanking(array $strongestPaths, array $allResponseIds)
	{
		$ranking = [];
		$remaining = $allResponseIds;
		$position = 1;

		while (!empty($remaining))
		{
			$roundWinners = [];

			// Find all winners in this round (may be multiple if tie)
			foreach ($remaining as $i)
			{
				$beatsAll = true;

				foreach ($remaining as $j)
				{
					if ($i == $j) continue;

					// i beats j if p[i][j] > p[j][i]
					if ($strongestPaths[$i][$j] <= $strongestPaths[$j][$i])
					{
						$beatsAll = false;
						break;
					}
				}

				if ($beatsAll)
				{
					$roundWinners[] = $i;
				}
			}

			// If no clear winner, everyone remaining ties
			if (empty($roundWinners))
			{
				foreach ($remaining as $r)
				{
					$ranking[$r] = $position;
				}
				break;
			}

			// Assign rank to this round's winners and remove them
			foreach ($roundWinners as $winner)
			{
				$ranking[$winner] = $position;
				$remaining = array_diff($remaining, [$winner]);
			}

			$position++;
		}

		return $ranking;
	}

	/**
	 * Get detailed pairwise comparison results
	 *
	 * Useful for displaying "Option A beats Option B by X votes" information
	 *
	 * @param array $preferences Preference matrix
	 * @param array $allResponseIds All response IDs
	 * @return array Pairwise comparison details
	 */
	public function getPairwiseComparisons(array $preferences, array $allResponseIds)
	{
		$comparisons = [];

		foreach ($allResponseIds as $i)
		{
			foreach ($allResponseIds as $j)
			{
				if ($i >= $j) continue; // Only process each pair once

				$iOverJ = $preferences[$i][$j];
				$jOverI = $preferences[$j][$i];

				$comparisons[] = [
					'option_a' => $i,
					'option_b' => $j,
					'a_over_b' => $iOverJ,
					'b_over_a' => $jOverI,
					'winner' => $iOverJ > $jOverI ? $i : ($jOverI > $iOverJ ? $j : null),
					'margin' => abs($iOverJ - $jOverI)
				];
			}
		}

		return $comparisons;
	}
}
