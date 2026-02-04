/**
 * Collection Selector Component
 *
 * Dropdown to select an article set (collection) to manage.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { SelectControl, Spinner, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import CollectionModal from './CollectionModal';

export default function CollectionSelector( { value, onChange } ) {
	const [ collections, setCollections ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ editingCollection, setEditingCollection ] = useState( null );

	const fetchCollections = useCallback( () => {
		setIsLoading( true );
		return apiFetch( {
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

	useEffect( () => {
		fetchCollections();
	}, [ fetchCollections ] );

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

	const openAddModal = () => {
		setEditingCollection( null );
		setIsModalOpen( true );
	};

	const openEditModal = () => {
		if ( ! value ) {
			return;
		}
		const current = collections.find( ( collection ) => collection.id === value );
		setEditingCollection( current || null );
		setIsModalOpen( true );
	};

	const handleModalClose = () => {
		setIsModalOpen( false );
		setEditingCollection( null );
	};

	const handleModalSave = ( data ) => {
		setIsSaving( true );
		const request = editingCollection
			? apiFetch( {
					path: '/wp/v2/richie_article_set/' + editingCollection.id,
					method: 'PUT',
					data,
				} )
			: apiFetch( {
					path: '/wp/v2/richie_article_set',
					method: 'POST',
					data,
				} );

		return request
			.then( ( response ) => {
				return fetchCollections().then( () => {
					if ( response && response.id ) {
						onChange( response.id );
					}
				} );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to save collection:', err );
			} )
			.finally( () => {
				setIsSaving( false );
				handleModalClose();
			} );
	};

	return (
		<div className="collection-selector">
			<SelectControl
				label={ __( 'Collection', 'richie' ) }
				value={ value || '' }
				options={ options }
				onChange={ ( newValue ) =>
					onChange( newValue ? parseInt( newValue, 10 ) : null )
				}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<div className="collection-selector__actions">
				<Button
					variant="secondary"
					onClick={ openAddModal }
					isBusy={ isSaving }
					disabled={ isSaving }
				>
					{ __( 'Add collection', 'richie' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ openEditModal }
					disabled={ isSaving || ! value }
				>
					{ __( 'Edit collection', 'richie' ) }
				</Button>
			</div>

			<CollectionModal
				isOpen={ isModalOpen }
				collection={ editingCollection }
				isSaving={ isSaving }
				onSave={ handleModalSave }
				onClose={ handleModalClose }
			/>
		</div>
	);
}
