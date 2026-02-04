/**
 * Collection Selector Component
 *
 * Dropdown to select an article set (collection) to manage.
 */

import { useState, useEffect } from '@wordpress/element';
import { SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function CollectionSelector( { value, onChange } ) {
	const [ collections, setCollections ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		apiFetch( {
			path: '/richie/v1/editor/collections',
		} )
			.then( ( response ) => {
				setCollections( response || [] );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to fetch collections:', err );
				setCollections( [] );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [] );

	if ( isLoading ) {
		return (
			<div className="collection-selector collection-selector--loading">
				<Spinner />
				<span>{ __( 'Loading collections...', 'richie' ) }</span>
			</div>
		);
	}

	const options = [
		{ label: __( '— Select a collection —', 'richie' ), value: '' },
		...collections.map( ( collection ) => ( {
			label: collection.name,
			value: collection.id.toString(),
		} ) ),
	];

	return (
		<div className="collection-selector">
			<SelectControl
				label={ __( 'Collection', 'richie' ) }
				value={ value || '' }
				options={ options }
				onChange={ ( newValue ) =>
					onChange( newValue ? parseInt( newValue, 10 ) : null )
				}
			/>
		</div>
	);
}
