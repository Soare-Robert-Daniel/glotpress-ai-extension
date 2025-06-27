import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import './dashboard.scss';
import { Dashboard } from './Dashboard';

domReady( () => {
	const rootElem = document.getElementById( 'glotpress-ai-dashboard' );
	if ( ! rootElem ) {
		return;
	}
	const root = createRoot( rootElem );
	root.render( <Dashboard /> );
} );
