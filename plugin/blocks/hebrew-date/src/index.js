/**
 * Hebrew Date block registration.
 *
 * Registers the hebrew-dates/hebrew-date block using metadata
 * from block.json, with the Edit and save components.
 *
 * @package Hebrew_Dates_Admin
 * @since   1.1.0
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';
import save from './save';

// Import styles — webpack compiles these into separate CSS files.
// style.scss  → loaded on both front-end and editor.
// editor.scss → loaded only in the editor.
import './style.scss';
import './editor.scss';

// Register the block using metadata from block.json.
// All attributes, supports, title, description, etc. are read
// from block.json. We only need to provide the JS components.
registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
