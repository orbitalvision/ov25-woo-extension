/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Dropdown } from '@wordpress/components';
import * as Woo from '@woocommerce/components';
import { Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './index.scss';

const MyExamplePage = () => (
	<Fragment>
		
	</Fragment>
);

// addFilter('woocommerce_admin_pages_list', 'ov25-woo-extension', (pages) => {
// 	pages.push({
// 		container: MyExamplePage,
// 		path: '/ov25-woo-extension',
// 		breadcrumbs: [__('Ov25 Woo Extension', 'ov25-woo-extension')],
// 		navArgs: {
// 			id: 'ov25_woo_extension',
// 		},
// 	});

// 	return pages;
// });
