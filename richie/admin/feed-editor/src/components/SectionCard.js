/**
 * Section Card Component
 *
 * Displays a draggable card for a news section with preview.
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { dragHandle, edit, trash } from '@wordpress/icons';
import { Icon } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const DEFAULT_LAYOUT_OPTIONS = [
	{ label: __( 'Featured', 'richie' ), value: 'featured' },
	{ label: __( 'Small', 'richie' ), value: 'small' },
];

const LAYOUT_OPTIONS =
	typeof window !== 'undefined' &&
	window.richieFeedEditorSettings &&
	Array.isArray( window.richieFeedEditorSettings.layoutOptions )
		? window.richieFeedEditorSettings.layoutOptions.map( ( option ) => ( {
			label: option.label || option.title || option.value,
			value: option.value,
		} ) )
		: DEFAULT_LAYOUT_OPTIONS;

const LAYOUT_LABELS = LAYOUT_OPTIONS.reduce( ( labels, option ) => {
	labels[ option.value ] = option.label;
	return labels;
}, {} );

const LAYOUT_ICONS = {
	featured: 'format-image',
	small: 'list-view',
};

export default function SectionCard( { section, onEdit, onDelete } ) {
	const [ preview, setPreview ] = useState( null );
	const [ isLoadingPreview, setIsLoadingPreview ] = useState( false );
	const [ isExpanded, setIsExpanded ] = useState( false );

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

	const backgroundColor = section.background_color
		? `#${ section.background_color }`
		: null;

	const cardStyle = backgroundColor
		? {
			...style,
			borderLeftColor: backgroundColor,
			borderLeftWidth: '4px',
		}
		: style;

	const previewDeps = JSON.stringify( {
		id: section.id,
		number_of_posts: section.number_of_posts,
		order_by: section.order_by,
		order_direction: section.order_direction,
		categories: section.categories,
		tags: section.tags,
		max_age: section.max_age,
		post_type: section.post_type,
	} );

	// Fetch preview data
	useEffect( () => {
		if ( section.id === undefined || section.id === null ) return;

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
	}, [ previewDeps ] );

	const layoutStyle = section.list_layout_style || 'none';
	const layoutLabel = LAYOUT_LABELS[ layoutStyle ] || layoutStyle;
	const layoutIcon = LAYOUT_ICONS[ layoutStyle ] || 'admin-post';
	const configuredCount = parseInt( section.number_of_posts, 10 ) || 0;

	const handleDelete = () => {
		if (
			window.confirm(
				__( 'Are you sure you want to delete this section?', 'richie' )
			)
		) {
			onDelete();
		}
	};

	let headerTitle = section.list_group_title || '';
	if ( ! headerTitle && preview && preview.articles && preview.articles.length > 0 ) {
		const headerArticle = preview.articles.find(
			( article ) => article.collection_header_title
		);
		headerTitle = headerArticle ? headerArticle.collection_header_title : '';
	}

	const previewCount = preview && preview.articles ? preview.articles.length : 0;
	const showPreviewCountNote =
		configuredCount > 0 && previewCount > 0 && previewCount < configuredCount;
	const configuredCountLabel = showPreviewCountNote
		? sprintf(
			__( '%1$d articles (%2$d available)', 'richie' ),
			configuredCount,
			previewCount
		)
		: sprintf( __( '%d articles', 'richie' ), configuredCount );

	return (
		<div
			ref={ setNodeRef }
			style={ cardStyle }
			className={ `feed-item-card section-card${
				isDragging ? ' is-dragging' : ''
			}${ backgroundColor ? ' has-background-color' : '' }` }
		>
			<div className="card-drag-handle" { ...attributes } { ...listeners }>
				<Icon icon={ dragHandle } />
			</div>

			<div className="card-content">
				<div className="card-header">
					{ backgroundColor && (
						<span
							className="section-color-swatch"
							style={ { backgroundColor } }
							aria-hidden="true"
						/>
					) }
					<div className="card-header-details">
						<div className="card-title-row">
							<div className="card-title-group">
								<strong className="card-title">{ section.name }</strong>
								<span className="card-meta">
									{ configuredCountLabel }
								</span>
							</div>
							<div className="card-layout-badge">
								<span
									className={ `dashicons dashicons-${ layoutIcon }` }
								></span>
								<span className="layout-label">{ layoutLabel }</span>
							</div>
						</div>
					</div>
				</div>

				<div className="card-preview">
					{ isLoadingPreview ? (
						<Spinner />
					) : preview && preview.articles && preview.articles.length > 0 ? (
						<>
							{ headerTitle && (
								<h4 className="preview-header-title">{ headerTitle }</h4>
							) }
							<ul className={ `preview-list${ isExpanded ? ' preview-list--expanded' : '' }` }>
								{ ( isExpanded ? preview.articles : preview.articles.slice( 0, 3 ) )
									.map( ( article, index ) => (
										<li key={ index } className="preview-item">
											{ article.thumbnail && (
												<img
													src={ article.thumbnail }
													alt=""
													className="preview-thumbnail"
												/>
											) }
											<span className="preview-title">{ article.title }</span>
										</li>
									) ) }
							</ul>
							{ preview.articles.length > 3 && (
								<Button
									variant="link"
									onClick={ () => setIsExpanded( ! isExpanded ) }
									className="preview-toggle"
								>
									{ isExpanded
										? __( 'Show less', 'richie' )
										: `+${ preview.articles.length - 3 } ${ __( 'more', 'richie' ) }` }
								</Button>
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
					isSmall
				/>
				<Button
					icon={ trash }
					label={ __( 'Delete', 'richie' ) }
					onClick={ handleDelete }
					isDestructive
					isSmall
				/>
			</div>
		</div>
	);
}
