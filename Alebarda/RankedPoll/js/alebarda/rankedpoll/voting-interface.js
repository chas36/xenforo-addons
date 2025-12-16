/**
 * Ranked Poll Voting Interface
 * Handles drag-and-drop ranking of poll options
 */

!function($, window, document) {
	'use strict';

	XF.RankedPollVoter = XF.Element.newHandler({
		options: {},

		$dragInterface: null,
		$fallbackInterface: null,
		$unrankedList: null,
		$rankedList: null,
		$emptyState: null,

		init: function() {
			this.$dragInterface = this.$target.find('.rankedPoll-dragInterface');
			this.$fallbackInterface = this.$target.find('.rankedPoll-fallbackInterface');
			this.$unrankedList = this.$target.find('.rankedPoll-unranked');
			this.$rankedList = this.$target.find('.rankedPoll-ranked');
			this.$emptyState = this.$target.find('.rankedPoll-emptyState');

			// Check if JS is available
			if (typeof Sortable !== 'undefined') {
				this.initDragDrop();
			} else {
				this.useFallback();
			}

			// Handle form submission
			this.$target.on('submit', $.proxy(this, 'onSubmit'));
		},

		initDragDrop: function() {
			var self = this;

			// Show drag interface, hide fallback
			this.$dragInterface.show().attr('data-has-js', 'true');
			this.$fallbackInterface.hide();

			// Initialize Sortable.js for both lists
			var sortableOptions = {
				group: 'ranked-poll',
				animation: 150,
				handle: '.rankedPoll-dragHandle',
				ghostClass: 'rankedPoll-item--ghost',
				dragClass: 'rankedPoll-item--dragging',
				onEnd: function(evt) {
					self.updateRankNumbers();
					self.updateEmptyState();
				}
			};

			// Unranked list
			new Sortable(this.$unrankedList[0], sortableOptions);

			// Ranked list
			new Sortable(this.$rankedList[0], sortableOptions);

			this.updateRankNumbers();
			this.updateEmptyState();
		},

		useFallback: function() {
			// Hide drag interface, show fallback
			this.$dragInterface.hide();
			this.$fallbackInterface.show().attr('data-has-js', 'false');
		},

		updateRankNumbers: function() {
			// Update rank numbers in ranked list
			this.$rankedList.find('.rankedPoll-item').each(function(index) {
				$(this).find('.rankedPoll-rank').text((index + 1) + '.');
			});
		},

		updateEmptyState: function() {
			if (this.$rankedList.find('.rankedPoll-item').length === 0) {
				this.$emptyState.show();
			} else {
				this.$emptyState.hide();
			}
		},

		onSubmit: function(e) {
			var $form = $(e.target);

			// If using drag-drop interface, build the ranked_responses array
			if (this.$dragInterface.is(':visible')) {
				// Clear any existing hidden inputs
				$form.find('input[name="ranked_responses[]"]').remove();

				// Add hidden inputs for each ranked item in order
				this.$rankedList.find('.rankedPoll-item').each(function() {
					var responseId = $(this).data('response-id');
					$('<input>')
						.attr('type', 'hidden')
						.attr('name', 'ranked_responses[]')
						.val(responseId)
						.appendTo($form);
				});
			}

			// Let form submit naturally
			return true;
		}
	});

	XF.Element.register('ranked-poll-voter', 'XF.RankedPollVoter');

}(jQuery, window, document);
