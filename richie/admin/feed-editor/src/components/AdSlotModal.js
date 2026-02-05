/**
 * Ad Slot Modal Component
 *
 * Modal dialog for adding/editing an ad slot.
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Modal,
	Button,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PROVIDER_OPTIONS = [
	{ label: __( 'Smart', 'richie' ), value: 'smart' },
	{ label: __( 'Google', 'richie' ), value: 'google' },
	{ label: __( 'Readpeak', 'richie' ), value: 'readpeak' },
];

const defaultFormData = {
	ad_provider: 'smart',
	ad_data: '',
};

const exampleAdData = `{
  "alternatives": [
    {
      "page_id": 123456,
      "format_id": 12345,
      "min_width": 451
    },
    {
      "page_id": 123457,
      "format_id": 12345,
      "max_width": 450
    }
  ]
}`;

export default function AdSlotModal( { adSlot, collectionId, onSave, onClose } ) {
	const [ formData, setFormData ] = useState( defaultFormData );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ jsonError, setJsonError ] = useState( null );

	const isEditing = !! adSlot;

	// Populate form when editing
	useEffect( () => {
		if ( adSlot ) {
			setFormData( {
				ad_provider: adSlot.ad_provider || 'smart',
				ad_data: adSlot.ad_data
					? JSON.stringify( adSlot.ad_data, null, 2 )
					: '',
			} );
		}
	}, [ adSlot ] );

	const updateField = ( field, value ) => {
		setFormData( ( prev ) => ( { ...prev, [ field ]: value } ) );

		// Validate JSON when ad_data changes
		if ( field === 'ad_data' ) {
			if ( value.trim() === '' ) {
				setJsonError( null );
			} else {
				try {
					JSON.parse( value );
					setJsonError( null );
				} catch ( e ) {
					setJsonError( e.message );
				}
			}
		}
	};

	const handleSubmit = () => {
		// Validate JSON before saving
		let parsedAdData = null;
		if ( formData.ad_data.trim() ) {
			try {
				parsedAdData = JSON.parse( formData.ad_data );
			} catch ( e ) {
				setJsonError( e.message );
				return;
			}
		}

		setIsSaving( true );
		onSave( {
			ad_provider: formData.ad_provider,
			ad_data: parsedAdData,
			article_set: collectionId,
		} )
			.catch( ( err ) => {
				console.error( 'Failed to save ad slot:', err );
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	};

	const insertExample = () => {
		updateField( 'ad_data', exampleAdData );
	};

	return (
		<Modal
			title={
				isEditing
					? __( 'Edit ad slot', 'richie' )
					: __( 'Add ad slot', 'richie' )
			}
			onRequestClose={ onClose }
			className="adslot-modal"
		>
			<div className="adslot-modal-content">
				<div className="adslot-modal-row">
					<SelectControl
						label={ __( 'Ad Provider', 'richie' ) }
						value={ formData.ad_provider }
						options={ PROVIDER_OPTIONS }
						onChange={ ( value ) =>
							updateField( 'ad_provider', value )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="adslot-modal-row">
					<TextareaControl
						label={ __( 'Ad Data (JSON)', 'richie' ) }
						value={ formData.ad_data }
						onChange={ ( value ) => updateField( 'ad_data', value ) }
						rows={ 10 }
						help={
							jsonError ? (
								<span className="adslot-json-error">
									{ __( 'Invalid JSON:', 'richie' ) }{ ' ' }
									{ jsonError }
								</span>
							) : (
								__( 'Optional JSON configuration for the ad', 'richie' )
							)
						}
					/>
					<Button variant="link" onClick={ insertExample }>
						{ __( 'Insert example', 'richie' ) }
					</Button>
				</div>
			</div>

			<div className="adslot-modal-footer">
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Cancel', 'richie' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleSubmit }
					isBusy={ isSaving }
					disabled={ isSaving || !! jsonError }
				>
					{ isEditing
						? __( 'Update ad slot', 'richie' )
						: __( 'Add ad slot', 'richie' ) }
				</Button>
			</div>
		</Modal>
	);
}
