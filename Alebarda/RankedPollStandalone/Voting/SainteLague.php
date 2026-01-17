<?php

namespace Alebarda\RankedPollStandalone\Voting;

class SainteLague
{
    /**
     * Распределить места методом Сент-Лагю
     *
     * @param array $votes Массив голосов: [user_id => [option_id => rank, ...], ...]
     * @param array $ranking Финальное ранжирование Шульце
     * @param int $totalSeats Общее количество мест для распределения
     * @return array ['allocations' => [option_id => seats], 'details' => [...]]
     */
    public function allocateSeats(array $votes, array $ranking, $totalSeats)
    {
        if ($totalSeats <= 0 || !$ranking) {
            return [
                'allocations' => [],
                'details' => [],
                'first_choice_votes' => []
            ];
        }

        $firstChoiceVotes = $this->countFirstChoiceVotes($votes, $ranking);
        $totalVotes = array_sum($firstChoiceVotes);
        if ($totalVotes === 0) {
            return [
                'allocations' => [],
                'details' => [],
                'first_choice_votes' => $firstChoiceVotes
            ];
        }

        $seatsWon = [];
        foreach ($ranking as $optionId) {
            $seatsWon[$optionId] = 0;
        }

        $allocationDetails = [];

        for ($seat = 1; $seat <= $totalSeats; $seat++) {
            $maxQuotient = -1;
            $winnerOptionId = null;

            foreach ($firstChoiceVotes as $optionId => $voteCount) {
                $divisor = (2 * $seatsWon[$optionId]) + 1;
                $quotient = $voteCount / $divisor;

                if ($quotient > $maxQuotient) {
                    $maxQuotient = $quotient;
                    $winnerOptionId = $optionId;
                }
            }

            if ($winnerOptionId !== null) {
                $seatsWon[$winnerOptionId]++;

                $allocationDetails[] = [
                    'seat' => $seat,
                    'option_id' => $winnerOptionId,
                    'votes' => $firstChoiceVotes[$winnerOptionId],
                    'divisor' => (2 * ($seatsWon[$winnerOptionId] - 1)) + 1,
                    'quotient' => $maxQuotient,
                    'total_seats_now' => $seatsWon[$winnerOptionId]
                ];
            }
        }

        $allocations = array_filter($seatsWon, function($seats) {
            return $seats > 0;
        });

        return [
            'allocations' => $allocations,
            'details' => $allocationDetails,
            'first_choice_votes' => $firstChoiceVotes
        ];
    }

    /**
     * Подсчитать голоса первого выбора
     */
    protected function countFirstChoiceVotes(array $votes, array $ranking)
    {
        $counts = [];

        foreach ($ranking as $optionId) {
            $counts[$optionId] = 0;
        }

        foreach ($votes as $userVote) {
            $minRank = PHP_INT_MAX;
            $firstChoice = null;

            foreach ($userVote as $optionId => $rank) {
                if ($rank < $minRank) {
                    $minRank = $rank;
                    $firstChoice = $optionId;
                }
            }

            if ($firstChoice !== null && array_key_exists($firstChoice, $counts)) {
                $counts[$firstChoice]++;
            }
        }

        return $counts;
    }
}
