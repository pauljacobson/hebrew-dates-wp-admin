/**
 * Edit component for the Hebrew Date block.
 *
 * Renders a live server-side preview in the editor using ServerSideRender,
 * and provides InspectorControls for toggling display options and
 * text alignment. Typography, color, and spacing controls are provided
 * automatically by WordPress via the block supports declared in block.json.
 *
 * @package Hebrew_Dates_Admin
 * @since   1.1.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Editor component for the Hebrew Date block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes from block.json.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @return {Element} Block editor markup.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		showEvents,
		showGregorianDate,
		showTransliteration,
		textAlignment,
	} = attributes;

	// useBlockProps() returns the props that must be spread on the block's
	// outermost wrapper element. It includes CSS classes for block
	// identification and any applied block supports styles.
	const blockProps = useBlockProps();

	return (
		<>
			{ /* Inspector Controls — appears in the block settings sidebar. */ }
			<InspectorControls>
				<PanelBody
					title={ __( 'Display Settings', 'hebrew-dates-admin' ) }
					initialOpen={ true }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show transliteration',
							'hebrew-dates-admin'
						) }
						help={
							showTransliteration
								? __(
										'Showing transliterated Hebrew date (e.g., "1 Tevet 5785").',
										'hebrew-dates-admin'
								  )
								: __(
										'Transliterated date is hidden.',
										'hebrew-dates-admin'
								  )
						}
						checked={ showTransliteration }
						onChange={ ( value ) =>
							setAttributes( { showTransliteration: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Gregorian date',
							'hebrew-dates-admin'
						) }
						help={
							showGregorianDate
								? __(
										"Showing today's Gregorian date alongside the Hebrew date.",
										'hebrew-dates-admin'
								  )
								: __(
										'Gregorian date is hidden.',
										'hebrew-dates-admin'
								  )
						}
						checked={ showGregorianDate }
						onChange={ ( value ) =>
							setAttributes( { showGregorianDate: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show events and holidays',
							'hebrew-dates-admin'
						) }
						help={
							showEvents
								? __(
										'Showing Jewish holidays and events for today.',
										'hebrew-dates-admin'
								  )
								: __(
										'Events and holidays are hidden.',
										'hebrew-dates-admin'
								  )
						}
						checked={ showEvents }
						onChange={ ( value ) =>
							setAttributes( { showEvents: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Alignment', 'hebrew-dates-admin' ) }
					initialOpen={ false }
				>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __(
							'Text alignment',
							'hebrew-dates-admin'
						) }
						value={ textAlignment }
						options={ [
							{
								value: 'left',
								label: __( 'Left', 'hebrew-dates-admin' ),
							},
							{
								value: 'center',
								label: __( 'Center', 'hebrew-dates-admin' ),
							},
							{
								value: 'right',
								label: __( 'Right', 'hebrew-dates-admin' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { textAlignment: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			{ /* Block content — live server-rendered preview.
			     ServerSideRender calls the block renderer REST endpoint,
			     which executes render.php with the current attributes.
			     The preview is always identical to the front-end output. */ }
			<div { ...blockProps }>
				<ServerSideRender
					block="hebrew-dates/hebrew-date"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
