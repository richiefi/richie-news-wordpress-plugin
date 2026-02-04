/**
 * Richie Feed Editor
 *
 * Visual drag-and-drop editor for managing news feed sections and ad slots.
 */

import './init'; // Initialize apiFetch middleware
import { createRoot } from '@wordpress/element';
import App from './App';
import './styles/editor.scss';

// Wait for DOM to be ready
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'feed-editor-root' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
