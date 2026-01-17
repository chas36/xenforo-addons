<?php

namespace Alebarda\RankedPollStandalone\Voting;

/**
 * Реализация метода Шульце для ranked-choice voting
 *
 * Метод Шульце - это Condorcet-совместимый метод голосования,
 * который определяет победителя на основе попарного сравнения кандидатов.
 *
 * Алгоритм:
 * 1. Построить pairwise preference matrix: d[i,j] = число избирателей, предпочитающих i перед j
 * 2. Вычислить strongest paths: p[i,j] = сила самого сильного пути от i к j
 * 3. Определить победителя: кандидат i побеждает j если p[i,j] > p[j,i]
 * 4. Condorcet winner = кандидат, побеждающий всех остальных
 */
class Schulze
{
    /**
     * Подсчитать результаты по методу Шульце
     *
     * @param array $votes Массив голосов: [user_id => [option_id => rank_position]]
     *                     Например: [
     *                         123 => [1 => 1, 2 => 2, 3 => 3],  // User 123: option 1 rank 1, option 2 rank 2...
     *                         456 => [2 => 1, 1 => 2, 3 => 3],
     *                     ]
     * @param array $candidates Массив ID кандидатов (option_id)
     * @return array Результаты: [
     *                   'winner_id' => int,
     *                   'ranking' => [option_id, ...],
     *                   'pairwise_matrix' => array,
     *                   'strongest_paths' => array
     *               ]
     */
    public function calculateWinner(array $votes, array $candidates)
    {
        if (empty($votes) || empty($candidates)) {
            return [
                'winner_id' => null,
                'ranking' => [],
                'pairwise_matrix' => [],
                'strongest_paths' => []
            ];
        }

        // Шаг 1: Построить pairwise preference matrix
        $d = $this->buildPairwiseMatrix($votes, $candidates);

        // Шаг 2: Вычислить strongest paths (Floyd-Warshall algorithm)
        $p = $this->computeStrongestPaths($d, $candidates);

        // Шаг 3: Определить ранжирование
        $ranking = $this->determineRanking($p, $candidates);

        return [
            'winner_id' => $ranking[0] ?? null,
            'ranking' => $ranking,
            'pairwise_matrix' => $d,
            'strongest_paths' => $p,
        ];
    }

    /**
     * Построить pairwise comparison matrix
     *
     * d[i,j] = количество избирателей, которые ранжируют i выше чем j
     *
     * @param array $votes
     * @param array $candidates
     * @return array
     */
    protected function buildPairwiseMatrix(array $votes, array $candidates)
    {
        $d = [];

        // Инициализация матрицы нулями
        foreach ($candidates as $i) {
            foreach ($candidates as $j) {
                $d[$i][$j] = 0;
            }
        }

        // Для каждого голоса
        foreach ($votes as $userId => $ballot) {
            // Сравнить каждую пару кандидатов
            foreach ($candidates as $i) {
                foreach ($candidates as $j) {
                    if ($i == $j) {
                        continue;
                    }

                    // Получить ранги (если не ранжирован = PHP_INT_MAX)
                    $rankI = isset($ballot[$i]) ? $ballot[$i] : PHP_INT_MAX;
                    $rankJ = isset($ballot[$j]) ? $ballot[$j] : PHP_INT_MAX;

                    // Если i ранжирован выше j (меньшее число = выше ранг)
                    if ($rankI < $rankJ) {
                        $d[$i][$j]++;
                    }
                }
            }
        }

        return $d;
    }

    /**
     * Вычислить strongest paths используя Floyd-Warshall algorithm
     *
     * p[i,j] = сила самого сильного пути от i к j
     *
     * @param array $d Pairwise matrix
     * @param array $candidates
     * @return array
     */
    protected function computeStrongestPaths(array $d, array $candidates)
    {
        $p = [];

        // Инициализация
        foreach ($candidates as $i) {
            foreach ($candidates as $j) {
                if ($i != $j) {
                    // Если i побеждает j напрямую
                    if ($d[$i][$j] > $d[$j][$i]) {
                        $p[$i][$j] = $d[$i][$j];
                    } else {
                        $p[$i][$j] = 0;
                    }
                }
            }
        }

        // Floyd-Warshall: найти сильнейшие пути через промежуточные вершины
        foreach ($candidates as $i) {
            foreach ($candidates as $j) {
                if ($i != $j) {
                    foreach ($candidates as $k) {
                        if ($i != $k && $j != $k) {
                            // Путь j -> i -> k может быть сильнее чем прямой путь j -> k
                            // Сила пути = минимум из силы его звеньев
                            $p[$j][$k] = max(
                                $p[$j][$k],
                                min($p[$j][$i], $p[$i][$k])
                            );
                        }
                    }
                }
            }
        }

        return $p;
    }

    /**
     * Определить ранжирование на основе strongest paths
     *
     * Кандидат i побеждает кандидата j если p[i,j] > p[j,i]
     *
     * @param array $p Strongest paths matrix
     * @param array $candidates
     * @return array Отсортированный массив candidate IDs (от победителя к проигравшим)
     */
    protected function determineRanking(array $p, array $candidates)
    {
        $scores = [];

        // Подсчитать количество побед для каждого кандидата
        foreach ($candidates as $i) {
            $wins = 0;
            $strengthSum = 0;

            foreach ($candidates as $j) {
                if ($i != $j) {
                    // i побеждает j
                    if ($p[$i][$j] > $p[$j][$i]) {
                        $wins++;
                        $strengthSum += $p[$i][$j];
                    }
                }
            }

            $scores[$i] = [
                'wins' => $wins,
                'strength' => $strengthSum
            ];
        }

        // Сортировать по количеству побед, затем по силе
        uasort($scores, function($a, $b) {
            if ($a['wins'] != $b['wins']) {
                return $b['wins'] - $a['wins']; // Больше побед = выше
            }
            return $b['strength'] - $a['strength']; // Больше сила = выше
        });

        return array_keys($scores);
    }

    /**
     * Проверить является ли кандидат Condorcet winner
     *
     * Condorcet winner = кандидат, который побеждает всех остальных в попарном сравнении
     *
     * @param int $candidateId
     * @param array $p Strongest paths matrix
     * @param array $candidates
     * @return bool
     */
    public function isCondorcetWinner($candidateId, array $p, array $candidates)
    {
        foreach ($candidates as $j) {
            if ($candidateId != $j) {
                // Если есть хотя бы один кандидат, который побеждает данного
                if ($p[$j][$candidateId] >= $p[$candidateId][$j]) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Получить детальную статистику для опции
     *
     * @param int $optionId
     * @param array $votes
     * @return array
     */
    public function getOptionStats($optionId, array $votes)
    {
        $stats = [
            'total_rankings' => 0,
            'rank_distribution' => [], // [rank => count]
            'average_rank' => 0,
            'median_rank' => 0,
        ];

        $ranks = [];

        foreach ($votes as $userId => $ballot) {
            if (isset($ballot[$optionId])) {
                $rank = $ballot[$optionId];
                $stats['total_rankings']++;
                $ranks[] = $rank;

                if (!isset($stats['rank_distribution'][$rank])) {
                    $stats['rank_distribution'][$rank] = 0;
                }
                $stats['rank_distribution'][$rank]++;
            }
        }

        if (!empty($ranks)) {
            $stats['average_rank'] = round(array_sum($ranks) / count($ranks), 2);

            sort($ranks);
            $middle = floor(count($ranks) / 2);
            if (count($ranks) % 2 == 0) {
                $stats['median_rank'] = ($ranks[$middle - 1] + $ranks[$middle]) / 2;
            } else {
                $stats['median_rank'] = $ranks[$middle];
            }
        }

        return $stats;
    }
}
