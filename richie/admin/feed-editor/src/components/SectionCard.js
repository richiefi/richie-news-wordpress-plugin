/**
 * Section Card Component
 *
 * Displays a draggable card for a news section with preview.
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { dragHandle, edit, trash } from '@wordpress/icons';
import { Icon } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const LAYOUT_LABELS = {
	featured: __( 'Featured', 'richie' ),
	big: __( 'Big', 'richie' ),
	small: __( 'Small', 'richie' ),
	small_group_item: __( 'Small Group', 'richie' ),
	full_width_text: __( 'Full Width', 'richie' ),
	text_left_square_thumb_right: __( 'Text + Thumb', 'richie' ),
	none: __( 'Default', 'richie' ),
};

const LAYOUT_ICONS = {
	featured: 'format-image',
	big: 'format-aside',
	small: 'list-view',
	small_group_item: 'grid-view',
	full_width_text: 'text',
	text_left_square_thumb_right: 'columns',
	none: 'admin-post',
};

export default function SectionCard( { section, onEdit, onDelete } ) {
	const [ preview, setPreview ] = useState( null );
	const [ isLoadingPreview, setIsLoadingPreview ] = useState( false );

	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: section.uniqueId } );

	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.5 : 1,
	};

	// Fetch preview data
	useEffect( () => {
		if ( ! section.id ) return;

		setIsLoadingPreview( true );
		apiFetch( {
			path: '/richie/v1/editor/preview/' + section.id,
		} )
			.then( ( response ) => {
				setPreview( response );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to fetch preview:', err );
				setPreview( null );
			} )
			.finally( () => {
				setIsLoadingPreview( false );
			} );
	}, [ section.id ] );

	const layoutStyle = section.list_layout_style || 'none';
	const layoutLabel = LAYOUT_LABELS[ layoutStyle ] || layoutStyle;
	const layoutIcon = LAYOUT_ICONS[ layoutStyle ] || 'admin-post';

	const handleDelete = () => {
		if (
			window.confirm(
				__( 'Are you sure you want to delete this section?', 'richie' )
			)
		) {
			onDelete();
		}
	};

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `feed-item-card section-card${
				isDragging ? ' is-dragging' : ''
			}` }
		>
			<div className="card-drag-handle" { ...attributes } { ...listeners }>
				<Icon icon={ dragHandle } />
			</div>

			<div className="card-layout-badge">
				<span className={ `dashicons dashicons-${ layoutIcon }` }></span>
				<span className="layout-label">{ layoutLabel }</span>
			</div>

			<div className="card-content">
				<div className="card-header">
					<strong className="card-title">{ section.name }</strong>
					<span className="card-meta">
						{ section.number_of_posts }{ ' ' }
						{ __( 'articles', 'richie' ) }
					</span>
				</div>

				<div className="card-preview">
					{ isLoadingPreview ? (
						<Spinner />
					) : preview && preview.articles ? (
						<>
							<ul className="preview-list">
								{ preview.articles
									.slice( 0, 3 )
									.map( ( article, index ) => (
										<li key={ index }>{ article.title }</li>
									) ) }
							</ul>
							{ preview.articles.length > 3 && (
								<span className="preview-more">
									+{ preview.articles.length - 3 }{ ' ' }
									{ __( 'more', 'richie' ) }
								</span>
							) }
						</>
					) : (
						<span className="preview-empty">
							{ __( 'No articles found', 'richie' ) }
						</span>
					) }
				</div>
			</div>

			<div className="card-actions">
				<Button
					icon={ edit }
					label={ __( 'Edit', 'richie' ) }
					onClick={ onEdit }
				/>
				<Button
					icon={ trash }
					label={ __( 'Delete', 'richie' ) }
					onClick={ handleDelete }
					isDestructive
				/>
			</div>
		</div>
	);
}
