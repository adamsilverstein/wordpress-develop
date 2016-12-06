/* global wp, jQuery, _ */
jQuery(function() {
	var originalSettings, sampleText;

	module( 'wp.formatting', {
		beforeEach: function() {
			originalSettings = _.clone( wp.formatting.settings );
			wp.formatting.settings.trimWordsMore = '…';
		},
		afterEach: function() {
			wp.formatting.settings = originalSettings;
		}
	});

	sampleText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec velit' +
		' tellus, porta ac nisl vitae, gravida rutrum risus. Integer porttitor rhoncus' +
		' convallis. Vestibulum et rutrum nulla. Aliquam efficitur vehicula tempor. Aliquam' +
		' sodales ligula vitae lorem vulputate, vitae sagittis turpis volutpat. Donec sapien' +
		' justo, facilisis eget dignissim vel, ultrices nec est. Vestibulum tempor purus dolor,' +
		' tincidunt suscipit diam consectetur eu.';

	test( 'trimWords() should default numWords to 55', function() {
		var expected, result;

		result = wp.formatting.trimWords( sampleText );
		expected = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec velit tellus,' +
			' porta ac nisl vitae, gravida rutrum risus. Integer porttitor rhoncus convallis.' +
			' Vestibulum et rutrum nulla. Aliquam efficitur vehicula tempor. Aliquam sodales' +
			' ligula vitae lorem vulputate, vitae sagittis turpis volutpat. Donec sapien justo,' +
			' facilisis eget dignissim vel, ultrices nec est. Vestibulum tempor purus dolor,' +
			' tincidunt…';

		equal( result, expected );
	});

	test( 'trimWords() accepts custom numWords', function() {
		var expected, result;

		result = wp.formatting.trimWords( sampleText, 4 );
		expected = 'Lorem ipsum dolor sit…';

		equal( result, expected );
	});

	test( 'trimWords() accepts custom truncation', function() {
		var expected, result;

		result = wp.formatting.trimWords( sampleText, 4, '...' );
		expected = 'Lorem ipsum dolor sit...';

		equal( result, expected );
	});

	test( 'trimWords() separated by character if defined by settings', function() {
		var expected, result;

		wp.formatting.settings.trimWordsByCharacter = true;

		result = wp.formatting.trimWords( sampleText, 4 );
		expected = 'Lore…';

		equal( result, expected );
	});

	test( 'trimWords() separates by any whitespace', function() {
		var modifiedSampleText, expected, result;

		modifiedSampleText = "Lorem\nipsum\tdolor sit\n\ramet";
		result = wp.formatting.trimWords( modifiedSampleText, 4 );
		expected = 'Lorem ipsum dolor sit…';

		equal( result, expected );
	});

	test( 'trimWords() does not truncate if fewer words than numWords', function() {
		var expected, result;

		result = wp.formatting.trimWords( sampleText, Infinity );

		equal( result, sampleText );
	});
});
