/**
 * useFeedItems Hook
 *
 * Manages feed items (sections and ad slots) for a collection.
 * Using Promise.then() instead of async/await to avoid regenerator-runtime issues.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function useFeedItems( collectionId ) {
	const [ items, setItems ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ hasUnsavedChanges, setHasUnsavedChanges ] = useState( false );
	const [ hasUnpublishedChanges, setHasUnpublishedChanges ] = useState( false );
	const [ originalOrder, setOriginalOrder ] = useState( [] );

	// Fetch items when collection changes
	useEffect( () => {
		if ( ! collectionId ) {
			setItems( [] );
			setError( null );
			return;
		}

		setIsLoading( true );
		setError( null );

		apiFetch( {
			path: '/richie/v1/editor/items/' + collectionId,
		} )
			.then( ( response ) => {
				const fetchedItems = response.items || [];
				setItems( fetchedItems );
				setOriginalOrder( fetchedItems.map( ( item ) => item.uniqueId ) );
				setHasUnsavedChanges( false );
				setHasUnpublishedChanges( !! response.has_unpublished_changes );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to fetch items:', err );
				setError( err.message || 'Failed to load items' );
				setItems( [] );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ collectionId ] );

	// Reorder items (drag-drop)
	const reorderItems = useCallback( ( fromIndex, toIndex ) => {
		setItems( ( prevItems ) => {
			const newItems = [ ...prevItems ];
			const [ movedItem ] = newItems.splice( fromIndex, 1 );
			newItems.splice( toIndex, 0, movedItem );
			return newItems;
		} );
		setHasUnsavedChanges( true );
	}, [] );

	// Save current order
	const saveOrder = useCallback( () => {
		if ( ! collectionId ) return Promise.resolve();

		return apiFetch( {
			path: '/richie/v1/editor/order/' + collectionId,
			method: 'POST',
			data: {
				items: items.map( ( item ) => ( {
					type: item.type,
					id: item.id,
				} ) ),
			},
		} )
			.then( () => {
				setOriginalOrder( items.map( ( item ) => item.uniqueId ) );
				setHasUnsavedChanges( false );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to save order:', err );
				throw err;
			} );
	}, [ collectionId, items ] );

	// Add a new section
	const addSection = useCallback( ( sectionData ) => {
		return apiFetch( {
			path: '/richie/v1/editor/section',
			method: 'POST',
			data: sectionData,
		} )
			.then( ( response ) => {
				// Add the new section to the end
				setItems( ( prevItems ) => [
					...prevItems,
					{
						...response,
						type: 'source',
						uniqueId: 'source-' + response.id,
					},
				] );
				setHasUnpublishedChanges( true );
				return response;
			} )
			.catch( ( err ) => {
				console.error( 'Failed to add section:', err );
				throw err;
			} );
	}, [] );

	// Update an existing section
	const updateSection = useCallback( ( sectionId, sectionData ) => {
		return apiFetch( {
			path: '/richie/v1/editor/section/' + sectionId,
			method: 'PUT',
			data: sectionData,
		} )
			.then( ( response ) => {
				// Update the section in the list
				setItems( ( prevItems ) =>
					prevItems.map( ( item ) =>
						item.type === 'source' && item.id === sectionId
							? { ...item, ...response }
							: item
					)
				);
				setHasUnpublishedChanges( true );
				return response;
			} )
			.catch( ( err ) => {
				console.error( 'Failed to update section:', err );
				throw err;
			} );
	}, [] );

	// Delete a section
	const deleteSection = useCallback( ( sectionId ) => {
		return apiFetch( {
			path: '/richie/v1/editor/section/' + sectionId,
			method: 'DELETE',
		} )
			.then( () => {
				// Remove from the list
				setItems( ( prevItems ) =>
					prevItems.filter(
						( item ) =>
							! ( item.type === 'source' && item.id === sectionId )
					)
				);
				setHasUnpublishedChanges( true );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to delete section:', err );
				throw err;
			} );
	}, [] );

	// Add a new ad slot
	const addAdSlot = useCallback( ( adSlotData ) => {
		return apiFetch( {
			path: '/richie/v1/editor/adslot',
			method: 'POST',
			data: adSlotData,
		} )
			.then( ( response ) => {
				// Add the new ad slot to the end
				setItems( ( prevItems ) => [
					...prevItems,
					{
						...response,
						type: 'ad',
						uniqueId: 'ad-' + response.id,
					},
				] );
				return response;
			} )
			.catch( ( err ) => {
				console.error( 'Failed to add ad slot:', err );
				throw err;
			} );
	}, [] );

	// Update an existing ad slot
	const updateAdSlot = useCallback( ( adSlotId, adSlotData ) => {
		return apiFetch( {
			path: '/richie/v1/editor/adslot/' + adSlotId,
			method: 'PUT',
			data: adSlotData,
		} )
			.then( ( response ) => {
				// Update the ad slot in the list
				setItems( ( prevItems ) =>
					prevItems.map( ( item ) =>
						item.type === 'ad' && item.id === adSlotId
							? { ...item, ...response }
							: item
					)
				);
				return response;
			} )
			.catch( ( err ) => {
				console.error( 'Failed to update ad slot:', err );
				throw err;
			} );
	}, [] );

	// Delete an ad slot
	const deleteAdSlot = useCallback(
		( adSlotId ) => {
			return apiFetch( {
				path: '/richie/v1/editor/adslot/' + adSlotId,
				method: 'DELETE',
				data: { collection_id: collectionId },
			} )
				.then( () => {
					// Remove from the list
					setItems( ( prevItems ) =>
						prevItems.filter(
							( item ) =>
								! ( item.type === 'ad' && item.id === adSlotId )
						)
					);
				} )
				.catch( ( err ) => {
					console.error( 'Failed to delete ad slot:', err );
					throw err;
				} );
		},
		[ collectionId ]
	);

	// Refresh items from server
	const refreshItems = useCallback( () => {
		if ( ! collectionId ) return Promise.resolve();

		setIsLoading( true );
		return apiFetch( {
			path: '/richie/v1/editor/items/' + collectionId,
		} )
			.then( ( response ) => {
				const fetchedItems = response.items || [];
				setItems( fetchedItems );
				setOriginalOrder( fetchedItems.map( ( item ) => item.uniqueId ) );
				setHasUnsavedChanges( false );
				setHasUnpublishedChanges( !! response.has_unpublished_changes );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to refresh items:', err );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ collectionId ] );

	// Publish source changes
	const publishSources = useCallback( () => {
		if ( ! window.richie_ajax || ! window.richie_ajax.ajax_url ) {
			return Promise.reject( new Error( 'Missing ajax configuration' ) );
		}

		const data = new URLSearchParams();
		data.append( 'action', 'publish_source_changes' );
		data.append( 'security', window.richie_ajax.security );

		return window
			.fetch( window.richie_ajax.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: data.toString(),
			} )
			.then( () => {
				setHasUnpublishedChanges( false );
				return refreshItems();
			} );
	}, [ refreshItems ] );

	// Revert source changes
	const revertSources = useCallback( () => {
		if ( ! window.richie_ajax || ! window.richie_ajax.ajax_url ) {
			return Promise.reject( new Error( 'Missing ajax configuration' ) );
		}

		const data = new URLSearchParams();
		data.append( 'action', 'revert_source_changes' );
		data.append( 'security', window.richie_ajax.security );

		return window
			.fetch( window.richie_ajax.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: data.toString(),
			} )
			.then( () => {
				setHasUnpublishedChanges( false );
				return refreshItems();
			} );
	}, [ refreshItems ] );

	return {
		items,
		isLoading,
		error,
		hasUnsavedChanges,
		hasUnpublishedChanges,
		reorderItems,
		addSection,
		updateSection,
		deleteSection,
		addAdSlot,
		updateAdSlot,
		deleteAdSlot,
		saveOrder,
		refreshItems,
		publishSources,
		revertSources,
	};
}
