'use strict';

( function ( $, undefined ) {
	var SM = {
		$wrapper: {},
		$container: {},
		conter: 0,

		init: function () {
			var _this = this;

			SM.$wrapper = $( ".sm-notifier-settings" );
            SM.$container = $( "ul", this.$wrapper );

            SM.counter = SM.$container.children().size();

            // check if there's only one option
            if ( 1 === SM.counter ) {
                var $temp_el = SM.$container.children().first();
                // check if the "value" select box has no options
                if ( 0 === $temp_el.find( ".sm-value option" ).size() ) {
                    // click the button with a timeout. Note that this is a hack that will need
                    // to be solved server-side
                    setTimeout( function () {
                        $temp_el.find( ".sm-category" ).change();
                    }, 300 );
                }
            }

			// when the "add" button is clicked
            SM.$container.on( 'click', '.sm-new-rule', function ( e ) {
				e.preventDefault();
				_this.addRule( $( this ).closest( 'li' ) );
			});

            SM.$container.on( 'click', '.sm-delete-rule', function ( e ) {
				e.preventDefault();

                // do not delete item if it's the only one left in the list
                if ( 1 === SM.$container.children().size() ) {
                    return;
                }

				_this.deleteRule( $( this ).closest( 'li' ) );
			});

			// handle change on action category selectbox
            SM.$container.on( 'change', '.sm-category', function ( e ) {
				e.preventDefault();

				var $select = $( this ),
					$siblings = $select.siblings( "select" );

				// disable all selectboxes to prevent multiple calls
				$siblings.filter( "select" ).prop( 'disabled', true );

				// grab live data via AJAX
				var data = _this.getData( $select.val(), function ( d ) {
					var $target = $siblings.filter( '.sm-value' );
					$target.empty(); // clear so we can insert fresh data

					$.each( d.data, function ( k, v ) {
						$target.append( $( "<option/>", {
							text: v,
							value: k
						} ) );
					});

					// restore disabled selectboxes
					$siblings.filter( "select" ).prop( 'disabled', false );
				});
			});

		},
		addRule: function ( $el ) {
			this.counter++;
			var $copy = $el.clone(),
				curID = parseInt( $el.data( 'id' ), null ),
				newID = this.counter;

			$copy.find( '[name]' ).each( function() {
				$( this ).attr( 'name', $( this ).attr( 'name' ).replace( curID, newID ) );
				// $( this ).attr( 'id', $( this ).attr( 'id' ).replace( curID, newID ) );
			});

			$copy.attr( 'data-id', newID );
			$el.after( $copy );
		},
		deleteRule: function ( $el ) {
			$el.remove();
		},
		getData: function ( type, cb ) {
			var payload = {
				action: 'sm_get_properties',
				action_category: type
			};
			$.getJSON( window.ajaxurl, payload, cb );
		}
	};

	$( function () {
		SM.init();
	});

	window.SM = SM;


	/**
	 * Form serialization helper
	 */
	$.fn.SMSerializeObject = function() {
		var o = {};
		var a = this.serializeArray();
		$.each( a, function() {
			if ( o[this.name] !== undefined ) {
				if ( !o[this.name].push ) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push( this.value || '' );
			} else {
				o[this.name] = this.value || '';
			}
		} );
		return o;
	};
})( jQuery );
