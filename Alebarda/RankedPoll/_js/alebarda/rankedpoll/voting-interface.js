/**
 * Ranked Poll Voting Interface
 * Provides drag-and-drop functionality for ranking poll options
 */

!function($, window, document, _undefined) {
	"use strict";

	XF.RankedPollVoter = XF.Element.newHandler({
		options: {},

		$dragInterface: null,
		$fallbackInterface: null,
		$unrankedList: null,
		$rankedList: null,
		$emptyState: null,
		sortableInstances: [],

		init: function() {
			this.$dragInterface = this.$target.find('.rankedPoll-dragInterface');
			this.$fallbackInterface = this.$target.find('.rankedPoll-fallbackInterface');
			this.$unrankedList = this.$target.find('#unrankedOptions');
			this.$rankedList = this.$target.find('#rankedOptions');
			this.$emptyState = this.$target.find('.rankedPoll-emptyState');

			// Check if Sortable library is available
			if (typeof Sortable !== 'undefined') {
				this.initDragAndDrop();
			} else {
				// Fallback to dropdown interface
				this.showFallback();
			}

			// Handle form submission
			this.$target.on('submit', XF.proxy(this, 'handleSubmit'));
		},

		/**
		 * Initialize drag-and-drop with Sortable.js
		 */
		initDragAndDrop: function() {
			var self = this;

			// Show drag interface, hide fallback
			this.$dragInterface.show();
			this.$fallbackInterface.hide();

			// Initialize Sortable on unranked list
			if (this.$unrankedList.length) {
				this.sortableInstances.push(Sortable.create(this.$unrankedList[0], {
					group: 'poll-options',
					animation: 150,
					handle: '.rankedPoll-dragHandle',
					ghostClass: 'rankedPoll-item--ghost',
					dragClass: 'rankedPoll-item--drag',
					onEnd: function() {
						self.updateRankNumbers();
						self.updateEmptyState();
					}
				}));
			}

			// Initialize Sortable on ranked list
			if (this.$rankedList.length) {
				this.sortableInstances.push(Sortable.create(this.$rankedList[0], {
					group: 'poll-options',
					animation: 150,
					handle: '.rankedPoll-dragHandle',
					ghostClass: 'rankedPoll-item--ghost',
					dragClass: 'rankedPoll-item--drag',
					onAdd: function() {
						self.updateRankNumbers();
						self.updateEmptyState();
					},
					onUpdate: function() {
						self.updateRankNumbers();
					},
					onRemove: function() {
						self.updateRankNumbers();
						self.updateEmptyState();
					}
				}));
			}

			this.updateRankNumbers();
			this.updateEmptyState();
		},

		/**
		 * Show fallback dropdown interface
		 */
		showFallback: function() {
			this.$dragInterface.hide();
			this.$fallbackInterface.show();
		},

		/**
		 * Update rank numbers in the ranked list
		 */
		updateRankNumbers: function() {
			this.$rankedList.find('.rankedPoll-item').each(function(index) {
				var $item = $(this);
				var $rankSpan = $item.find('.rankedPoll-rank');

				if ($rankSpan.length === 0) {
					// Add rank span if it doesn't exist
					$item.find('.rankedPoll-dragHandle').after(
						'<span class="rankedPoll-rank">' + (index + 1) + '.</span>'
					);
				} else {
					// Update existing rank
					$rankSpan.text((index + 1) + '.');
				}
			});

			// Remove rank numbers from unranked items
			this.$unrankedList.find('.rankedPoll-rank').remove();
		},

		/**
		 * Update empty state visibility
		 */
		updateEmptyState: function() {
			if (this.$rankedList.children('.rankedPoll-item').length === 0) {
				this.$emptyState.show();
			} else {
				this.$emptyState.hide();
			}
		},

		/**
		 * Handle form submission
		 */
		handleSubmit: function(e) {
			var $form = this.$target;
			var $dataContainer = $form.find('#rankedVotesData');

			// If using drag-and-drop, create hidden inputs with ranked order
			if (this.$dragInterface.is(':visible')) {
				$dataContainer.empty();

				var rankedItems = this.$rankedList.find('.rankedPoll-item');
				rankedItems.each(function(index) {
					var responseId = $(this).data('response-id');
					var $input = $('<input>')
						.attr('type', 'hidden')
						.attr('name', 'ranked_responses[]')
						.val(responseId);
					$dataContainer.append($input);
				});

				// Remove fallback dropdown values if present
				$form.find('select[name^="rankings"]').prop('disabled', true);
			}

			// Validation: at least one option must be ranked
			var hasRankedOptions = $dataContainer.find('input').length > 0 ||
				$form.find('select[name^="rankings"]').filter(function() {
					return $(this).val() !== '';
				}).length > 0;

			if (!hasRankedOptions) {
				e.preventDefault();
				XF.alert('Please rank at least one option before submitting your vote.');
				return false;
			}

			return true;
		}
	});

	XF.Element.register('ranked-poll-voter', 'XF.RankedPollVoter');

}(jQuery, window, document);
