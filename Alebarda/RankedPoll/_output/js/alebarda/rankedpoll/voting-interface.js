!function($, window, document) {
	"use strict";

	/**
	 * Ranked Poll Voter Handler
	 * Manages drag-and-drop voting interface for ranked polls
	 */
	XF.RankedPollVoter = XF.Element.newHandler({
		options: {},

		$form: null,
		$dragInterface: null,
		$fallbackInterface: null,
		$unrankedList: null,
		$rankedList: null,
		$emptyState: null,
		unrankedSortable: null,
		rankedSortable: null,

		init: function() {
			this.$form = this.$target;
			this.$dragInterface = this.$target.find('.rankedPoll-dragInterface');
			this.$fallbackInterface = this.$target.find('.rankedPoll-fallbackInterface');
			this.$unrankedList = this.$target.find('#unrankedOptions');
			this.$rankedList = this.$target.find('#rankedOptions');
			this.$emptyState = this.$target.find('.rankedPoll-emptyState');

			// Check if Sortable library is available
			if (typeof Sortable !== 'undefined') {
				this.initDragAndDrop();
				this.showDragInterface();
			} else {
				// Fallback to dropdown interface
				this.showFallbackInterface();
			}

			this.$form.on('submit', $.proxy(this, 'handleSubmit'));
		},

		/**
		 * Initialize Sortable.js on both lists
		 */
		initDragAndDrop: function() {
			var self = this;

			// Unranked options list
			this.unrankedSortable = Sortable.create(this.$unrankedList[0], {
				group: 'poll-options',
				animation: 150,
				handle: '.rankedPoll-dragHandle',
				onEnd: function(evt) {
					self.updateRankNumbers();
					self.updateEmptyState();
				}
			});

			// Ranked options list
			this.rankedSortable = Sortable.create(this.$rankedList[0], {
				group: 'poll-options',
				animation: 150,
				handle: '.rankedPoll-dragHandle',
				onEnd: function(evt) {
					self.updateRankNumbers();
					self.updateEmptyState();
				}
			});

			// Initial state
			this.updateRankNumbers();
			this.updateEmptyState();
		},

		/**
		 * Update rank numbers in the ranked list
		 */
		updateRankNumbers: function() {
			var $items = this.$rankedList.find('.rankedPoll-item');

			$items.each(function(index, item) {
				var $item = $(item);
				var $rank = $item.find('.rankedPoll-rank');

				if ($rank.length === 0) {
					// Add rank span if it doesn't exist
					$rank = $('<span class="rankedPoll-rank"></span>');
					$item.find('.rankedPoll-dragHandle').after($rank);
				}

				$rank.text((index + 1) + '.');
			});
		},

		/**
		 * Show/hide empty state message
		 */
		updateEmptyState: function() {
			var hasItems = this.$rankedList.find('.rankedPoll-item').length > 0;
			this.$emptyState.toggle(!hasItems);
		},

		/**
		 * Show drag interface, hide fallback
		 */
		showDragInterface: function() {
			this.$dragInterface.show();
			this.$fallbackInterface.hide();
		},

		/**
		 * Show fallback interface, hide drag
		 */
		showFallbackInterface: function() {
			this.$dragInterface.hide();
			this.$fallbackInterface.show();
		},

		/**
		 * Handle form submission
		 */
		handleSubmit: function(e) {
			// If using drag interface, serialize rankings
			if (this.$dragInterface.is(':visible')) {
				e.preventDefault();

				var rankings = this.serializeRankings();

				if (Object.keys(rankings).length === 0) {
					XF.alert('Please rank at least one option before submitting.');
					return false;
				}

				// Create hidden inputs for rankings
				var $dataContainer = this.$form.find('#rankedVotesData');
				$dataContainer.empty();

				$.each(rankings, function(responseId, rank) {
					$('<input type="hidden">')
						.attr('name', 'rankings[' + responseId + ']')
						.val(rank)
						.appendTo($dataContainer);
				});

				// Submit form
				this.$form.off('submit').submit();
			}
			// Fallback interface uses select dropdowns, no special handling needed
		},

		/**
		 * Serialize current rankings to object
		 * @returns {Object} { response_id: rank, ... }
		 */
		serializeRankings: function() {
			var rankings = {};

			this.$rankedList.find('.rankedPoll-item').each(function(index, item) {
				var responseId = $(item).data('response-id');
				var rank = index + 1;
				rankings[responseId] = rank;
			});

			return rankings;
		}
	});

	/**
	 * Register handler on page load
	 */
	XF.Element.register('ranked-poll-voter', 'XF.RankedPollVoter');

}(jQuery, window, document);
