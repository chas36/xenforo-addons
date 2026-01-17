<?php

namespace Alebarda\RankedPoll\Voting;

/**
 * Schulze Method implementation for ranked-choice voting
 *
 * The Schulze method is a Condorcet method that determines a winner
 * by comparing candidates pairwise and finding the strongest paths.
 */
class Schulze
{
	/**
	 * Calculate winner using Schulze method
	 *
	 * @param array $votes Format: [user_id => [response_id => rank]]
	 * @param array $candidates List of all candidate response_ids
	 * @return array ['winner_id' => int, 'ranking' => array, 'matrix' => array]
	 */
	public function calculateWinner(array $votes, array $candidates)
	{
		if (empty($votes) || empty($candidates))
		{
			return [
				'winner_id' => null,
				'ranking' => [],
				'matrix' => []
			];
		}

		// Step 1: Build pairwise preference matrix
		$pairwise = $this->buildPairwiseMatrix($votes, $candidates);

		// Step 2: Calculate strongest paths using Floyd-Warshall
		$strongest = $this->calculateStrongestPaths($pairwise, $candidates);

		// Step 3: Determine ranking based on strongest paths
		$ranking = $this->determineRanking($strongest, $candidates);

		return [
			'winner_id' => !empty($ranking) ? $ranking[0] : null,
			'ranking' => $ranking,
			'pairwise_matrix' => $pairwise,
			'strongest_paths' => $strongest
		];
	}

	/**
	 * Build pairwise preference matrix
	 *
	 * d[i,j] = number of voters who prefer candidate i over candidate j
	 *
	 * @param array $votes
	 * @param array $candidates
	 * @return array
	 */
	protected function buildPairwiseMatrix(array $votes, array $candidates)
	{
		$matrix = [];

		// Initialize matrix with zeros
		foreach ($candidates as $i)
		{
			foreach ($candidates as $j)
			{
				$matrix[$i][$j] = 0;
			}
		}

		// Count preferences
		foreach ($votes as $userId => $rankings)
		{
			// For each pair of candidates
			foreach ($candidates as $i)
			{
				foreach ($candidates as $j)
				{
					if ($i === $j) continue;

					$rankI = isset($rankings[$i]) ? $rankings[$i] : PHP_INT_MAX;
					$rankJ = isset($rankings[$j]) ? $rankings[$j] : PHP_INT_MAX;

					// If voter ranked i higher than j (lower rank number = higher preference)
					if ($rankI < $rankJ)
					{
						$matrix[$i][$j]++;
					}
				}
			}
		}

		return $matrix;
	}

	/**
	 * Calculate strongest paths using Floyd-Warshall algorithm
	 *
	 * p[i,j] = strength of the strongest path from i to j
	 *
	 * @param array $pairwise
	 * @param array $candidates
	 * @return array
	 */
	protected function calculateStrongestPaths(array $pairwise, array $candidates)
	{
		$paths = [];

		// Initialize: p[i,j] = d[i,j] if d[i,j] > d[j,i], else 0
		foreach ($candidates as $i)
		{
			foreach ($candidates as $j)
			{
				if ($i === $j)
				{
					$paths[$i][$j] = 0;
				}
				else if ($pairwise[$i][$j] > $pairwise[$j][$i])
				{
					$paths[$i][$j] = $pairwise[$i][$j];
				}
				else
				{
					$paths[$i][$j] = 0;
				}
			}
		}

		// Floyd-Warshall: find strongest indirect paths
		foreach ($candidates as $k)
		{
			foreach ($candidates as $i)
			{
				if ($i === $k) continue;

				foreach ($candidates as $j)
				{
					if ($j === $i || $j === $k) continue;

					// Strength of path i->k->j is min of i->k and k->j
					$indirectPath = min($paths[$i][$k], $paths[$k][$j]);

					// Update if this path is stronger
					if ($indirectPath > $paths[$i][$j])
					{
						$paths[$i][$j] = $indirectPath;
					}
				}
			}
		}

		return $paths;
	}

	/**
	 * Determine ranking of candidates
	 *
	 * Candidate i is ranked higher than j if p[i,j] > p[j,i]
	 *
	 * @param array $strongest
	 * @param array $candidates
	 * @return array Ordered array of candidate IDs (best to worst)
	 */
	protected function determineRanking(array $strongest, array $candidates)
	{
		// Calculate wins for each candidate
		$wins = [];
		foreach ($candidates as $i)
		{
			$wins[$i] = 0;
			foreach ($candidates as $j)
			{
				if ($i === $j) continue;

				// i beats j if strongest path from i to j is stronger than j to i
				if ($strongest[$i][$j] > $strongest[$j][$i])
				{
					$wins[$i]++;
				}
			}
		}

		// Sort candidates by number of wins (descending)
		arsort($wins);

		return array_keys($wins);
	}

	/**
	 * Get human-readable explanation of results
	 *
	 * @param array $results Results from calculateWinner()
	 * @param array $candidateNames Map of candidate_id => name
	 * @return string
	 */
	public function explainResults(array $results, array $candidateNames = [])
	{
		if (empty($results['winner_id']))
		{
			return "No votes cast.";
		}

		$explanation = [];
		$winnerId = $results['winner_id'];
		$winnerName = $candidateNames[$winnerId] ?? "Candidate $winnerId";

		$explanation[] = "Winner: $winnerName";
		$explanation[] = "";
		$explanation[] = "Ranking:";

		foreach ($results['ranking'] as $position => $candidateId)
		{
			$name = $candidateNames[$candidateId] ?? "Candidate $candidateId";
			$rank = $position + 1;
			$explanation[] = "$rank. $name";
		}

		return implode("\n", $explanation);
	}
}
