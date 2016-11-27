/* global wp */
( function( QUnit ) {
	wp.formatting.settings.trimWordsMore = '&hellip;';
	QUnit.module( 'wp-util' );
	var longText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius lacinia vehicula. Etiam sapien risus, ultricies ac posuere eu, convallis sit amet augue. Pellentesque urna massa, lacinia vel iaculis eget, bibendum in mauris. Aenean eleifend pulvinar ligula, a convallis eros gravida non. Suspendisse potenti. Pellentesque et odio tortor. In vulputate pellentesque libero, sed dapibus velit mollis viverra. Pellentesque id urna euismod dolor cursus sagittis.';

	QUnit.test( 'wp.formatting.trimWords', function( assert ) {
		_.each( [
			{
				'trimmed': 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius lacinia vehicula. Etiam sapien risus, ultricies ac posuere eu, convallis sit amet augue. Pellentesque urna massa, lacinia vel iaculis eget, bibendum in mauris. Aenean eleifend pulvinar ligula, a convallis eros gravida non. Suspendisse potenti. Pellentesque et odio tortor. In vulputate pellentesque libero, sed dapibus velit&hellip;',
				'text': longText,
				'description': 'Trims to 55 by default.'
			},
			{
				'trimmed': 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius&hellip;',
				'text': longText,
				'length': 10,
				'description': 'Trims to 10.'
			},
			{
				'trimmed': 'Lorem ipsum dolor sit amet,[...] Read on!',
				'text': longText,
				'description': 'Trims to 5 and uses custom more.',
				'length': 5,
				'more': '[...] Read on!'
			},
			{
				'trimmed': 'This is some short text.',
				'text': 'This is some short text.',
				'description': 'Doesn\'t strip short text.'
			}

		], function( test ) {
			assert.equal(
				wp.formatting.trimWords( test.text, test.length, test.more ),
				test.trimmed,
				test.description
			);
		} );
	} );
} )( window.QUnit );
( function( QUnit ) {
	wp.formatting.settings.trimWordsMore = '&hellip;';
	QUnit.module( 'wp-util' );
	var longText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius lacinia vehicula. Etiam sapien risus, ultricies ac posuere eu, convallis sit amet augue. Pellentesque urna massa, lacinia vel iaculis eget, bibendum in mauris. Aenean eleifend pulvinar ligula, a convallis eros gravida non. Suspendisse potenti. Pellentesque et odio tortor. In vulputate pellentesque libero, sed dapibus velit mollis viverra. Pellentesque id urna euismod dolor cursus sagittis.';

	QUnit.test( 'wp.formatting.trimWords', function( assert ) {
		_.each( [
			{
				'trimmed': 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius lacinia vehicula. Etiam sapien risus, ultricies ac posuere eu, convallis sit amet augue. Pellentesque urna massa, lacinia vel iaculis eget, bibendum in mauris. Aenean eleifend pulvinar ligula, a convallis eros gravida non. Suspendisse potenti. Pellentesque et odio tortor. In vulputate pellentesque libero, sed dapibus velit&hellip;',
				'text': longText,
				'description': 'Trims to 55 by default.'
			},
			{
				'trimmed': 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce varius&hellip;',
				'text': longText,
				'length': 10,
				'description': 'Trims to 10.'
			},
			{
				'trimmed': 'Lorem ipsum dolor sit amet,[...] Read on!',
				'text': longText,
				'description': 'Trims to 5 and uses custom more.',
				'length': 5,
				'more': '[...] Read on!'
			},
			{
				'trimmed': 'This is some short text.',
				'text': 'This is some short text.',
				'description': 'Doesn\'t strip short text.'
			}

		], function( test ) {
			assert.equal(
				wp.formatting.trimWords( test.text, test.length, test.more ),
				test.trimmed,
				test.description
			);
		} );
	} );
} )( window.QUnit );
