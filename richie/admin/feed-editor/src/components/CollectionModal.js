/**
 * Collection Modal Component
 *
 * Modal dialog for adding/editing collections (article sets).
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function CollectionModal( { isOpen, collection, isSaving, onSave, onClose } ) {
  const [ name, setName ] = useState( '' );
  const [ slug, setSlug ] = useState( '' );

  useEffect( () => {
    if ( collection ) {
      setName( collection.name || '' );
      setSlug( collection.slug || '' );
    } else {
      setName( '' );
      setSlug( '' );
    }
  }, [ collection ] );

  if ( ! isOpen ) {
    return null;
  }

  const handleSubmit = () => {
    if ( ! name ) {
      return;
    }
    onSave( { name, slug: slug || undefined } );
  };

  return (
    <Modal
      title={ collection ? __( 'Edit collection', 'richie' ) : __( 'Add collection', 'richie' ) }
      onRequestClose={ onClose }
      className="collection-modal"
    >
      <div className="collection-modal-content">
        <TextControl
          label={ __( 'Name', 'richie' ) }
          value={ name }
          onChange={ setName }
          __next40pxDefaultSize
          __nextHasNoMarginBottom
          required
        />
        <TextControl
          label={ __( 'Slug', 'richie' ) }
          value={ slug }
          onChange={ setSlug }
          __next40pxDefaultSize
          __nextHasNoMarginBottom
          help={ __( 'Used in feed URL (optional)', 'richie' ) }
        />
      </div>

      <div className="modal-footer collection-modal-footer">
        <Button variant="secondary" onClick={ onClose }>
          { __( 'Cancel', 'richie' ) }
        </Button>
        <Button
          variant="primary"
          onClick={ handleSubmit }
          isBusy={ isSaving }
          disabled={ isSaving || ! name }
        >
          { collection ? __( 'Update collection', 'richie' ) : __( 'Add collection', 'richie' ) }
        </Button>
      </div>
    </Modal>
  );
}
