/**
 * Cookie Settings block – editor.
 *
 * @package Oven
 */

(function () {
	'use strict';

	var registerBlockType = window.wp.blocks && window.wp.blocks.registerBlockType;
	var useBlockProps = window.wp.blockEditor && window.wp.blockEditor.useBlockProps;
	var __ = window.wp.i18n && window.wp.i18n.__;
	var InspectorControls = window.wp.blockEditor && window.wp.blockEditor.InspectorControls;
	var PanelBody = window.wp.components && window.wp.components.PanelBody;
	var TextControl = window.wp.components && window.wp.components.TextControl;

	if (!registerBlockType || !useBlockProps) return;

	registerBlockType('oven/cookie-settings', {
		edit: function (props) {
			var blockProps = useBlockProps ? useBlockProps() : {};
			var text = props.attributes.text || 'Cookie Settings';
			var setAttributes = props.setAttributes;

			return window.wp.element.createElement(
				window.wp.element.Fragment,
				null,
				InspectorControls && window.wp.element.createElement(
					InspectorControls,
					null,
					window.wp.element.createElement(
						PanelBody,
						{ title: __('Button', 'oven-cookie-consent'), initialOpen: true },
						TextControl && window.wp.element.createElement(TextControl, {
							label: __('Button text', 'oven-cookie-consent'),
							value: text,
							onChange: function (val) { setAttributes({ text: val || 'Cookie Settings' }); }
						})
					)
				),
				window.wp.element.createElement(
					'div',
					blockProps,
					window.wp.element.createElement(
						'button',
						{
							type: 'button',
							className: 'cookie-settings wp-block-button__link',
							disabled: true
						},
						text
					)
				)
			);
		},
		save: function () {
			return null;
		}
	});
})();
