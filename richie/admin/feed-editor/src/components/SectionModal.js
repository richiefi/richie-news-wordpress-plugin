/**
 * Section Modal Component
 *
 * Modal dialog for adding/editing a news section.
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Modal,
	Button,
	TextControl,
	SelectControl,
	CheckboxControl,
	__experimentalNumberControl as NumberControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const LAYOUT_OPTIONS = [
	{ label: __( 'Featured', 'richie' ), value: 'featured' },
	{ label: __( 'Big', 'richie' ), value: 'big' },
	{ label: __( 'Small', 'richie' ), value: 'small' },
	{ label: __( 'Small Group Item', 'richie' ), value: 'small_group_item' },
	{ label: __( 'Full Width Text', 'richie' ), value: 'full_width_text' },
	{
		label: __( 'Text + Thumbnail', 'richie' ),
		value: 'text_left_square_thumb_right',
	},
	{ label: __( 'None', 'richie' ), value: 'none' },
];

const ORDER_BY_OPTIONS = [
	{ label: __( 'Date', 'richie' ), value: 'date' },
	{ label: __( 'Modified', 'richie' ), value: 'modified' },
	{ label: __( 'Title', 'richie' ), value: 'title' },
	{ label: __( 'Author', 'richie' ), value: 'author' },
	{ label: __( 'ID', 'richie' ), value: 'id' },
];

const ORDER_DIRECTION_OPTIONS = [
	{ label: __( 'Descending', 'richie' ), value: 'DESC' },
	{ label: __( 'Ascending', 'richie' ), value: 'ASC' },
];

const MAX_AGE_OPTIONS = [
	{ label: __( '1 day', 'richie' ), value: '1 day' },
	{ label: __( '3 days', 'richie' ), value: '3 days' },
	{ label: __( '1 week', 'richie' ), value: '1 week' },
	{ label: __( '2 weeks', 'richie' ), value: '2 weeks' },
	{ label: __( '1 month', 'richie' ), value: '1 month' },
	{ label: __( '3 months', 'richie' ), value: '3 months' },
	{ label: __( '6 months', 'richie' ), value: '6 months' },
	{ label: __( '1 year', 'richie' ), value: '1 year' },
	{ label: __( 'All time', 'richie' ), value: 'all_time' },
];

const defaultFormData = {
	name: '',
	number_of_posts: 5,
	post_type: 'post',
	categories: [],
	tags: '',
	order_by: 'date',
	order_direction: 'DESC',
	max_age: 'all_time',
	list_layout_style: 'small',
	list_group_title: '',
	background_color: '',
	allow_duplicates: false,
	disable_summary: false,
};

export default function SectionModal( {
	section,
	collectionId,
	onSave,
	onClose,
} ) {
	const [ formData, setFormData ] = useState( defaultFormData );
	const [ categories, setCategories ] = useState( [] );
	const [ postTypes, setPostTypes ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );

	const isEditing = !! section;

	// Load categories and post types
	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/wp/v2/categories?per_page=100' } ),
			apiFetch( { path: '/richie/v1/editor/post-types' } ),
		] )
			.then( ( [ categoriesResponse, postTypesResponse ] ) => {
				setCategories( categoriesResponse || [] );
				setPostTypes( postTypesResponse || [] );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to fetch options:', err );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [] );

	// Populate form when editing
	useEffect( () => {
		if ( section ) {
			// Handle max_age: convert empty string or missing value to 'all_time'
			let maxAge = section.max_age;
			if ( ! maxAge || maxAge === '' ) {
				maxAge = 'all_time';
			}

			setFormData( {
				name: section.name || '',
				number_of_posts: section.number_of_posts || 5,
				post_type: section.post_type || 'post',
				categories: section.categories || [],
				tags: Array.isArray( section.tags )
					? section.tags.join( ', ' )
					: section.tags || '',
				order_by: section.order_by || 'date',
				order_direction: section.order_direction || 'DESC',
				max_age: maxAge,
				list_layout_style: section.list_layout_style || 'small',
				list_group_title: section.list_group_title || '',
				background_color: section.background_color || '',
				allow_duplicates: !! section.allow_duplicates,
				disable_summary: !! section.disable_summary,
			} );
		}
	}, [ section ] );

	const updateField = ( field, value ) => {
		setFormData( ( prev ) => ( { ...prev, [ field ]: value } ) );
	};

	const handleSubmit = () => {
		setIsSaving( true );

		// Convert tags string to array
		const tagsArray = formData.tags
			? formData.tags
					.split( ',' )
					.map( ( t ) => t.trim() )
					.filter( Boolean )
			: [];

		onSave( {
			...formData,
			tags: tagsArray,
			article_set: collectionId,
		} )
			.catch( ( err ) => {
				console.error( 'Failed to save section:', err );
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	};

	const handleCategoryToggle = ( categoryId ) => {
		setFormData( ( prev ) => {
			const current = prev.categories || [];
			const updated = current.includes( categoryId )
				? current.filter( ( id ) => id !== categoryId )
				: [ ...current, categoryId ];
			return { ...prev, categories: updated };
		} );
	};

	if ( isLoading ) {
		return (
			<Modal
				title={
					isEditing
						? __( 'Edit Section', 'richie' )
						: __( 'Add Section', 'richie' )
				}
				onRequestClose={ onClose }
			>
				<div className="section-modal-loading">
					<Spinner />
				</div>
			</Modal>
		);
	}

	return (
		<Modal
			title={
				isEditing
					? __( 'Edit Section', 'richie' )
					: __( 'Add Section', 'richie' )
			}
			onRequestClose={ onClose }
			className="section-modal"
		>
			<div className="section-modal-content">
				<div className="section-modal-row">
					<TextControl
						label={ __( 'Name', 'richie' ) }
						value={ formData.name }
						onChange={ ( value ) => updateField( 'name', value ) }
						required
					/>
				</div>

				<div className="section-modal-row section-modal-row--two-col">
					<NumberControl
						label={ __( 'Number of Articles', 'richie' ) }
						value={ formData.number_of_posts }
						onChange={ ( value ) =>
							updateField( 'number_of_posts', parseInt( value, 10 ) )
						}
						min={ 1 }
						max={ 50 }
					/>

					<SelectControl
						label={ __( 'Layout Style', 'richie' ) }
						value={ formData.list_layout_style }
						options={ LAYOUT_OPTIONS }
						onChange={ ( value ) =>
							updateField( 'list_layout_style', value )
						}
					/>
				</div>

				<div className="section-modal-row">
					<SelectControl
						label={ __( 'Post Type', 'richie' ) }
						value={ formData.post_type }
						options={ postTypes.map( ( pt ) => ( {
							label: pt.label,
							value: pt.name,
						} ) ) }
						onChange={ ( value ) => updateField( 'post_type', value ) }
					/>
				</div>

				<div className="section-modal-row section-modal-row--two-col">
					<SelectControl
						label={ __( 'Order By', 'richie' ) }
						value={ formData.order_by }
						options={ ORDER_BY_OPTIONS }
						onChange={ ( value ) => updateField( 'order_by', value ) }
					/>

					<SelectControl
						label={ __( 'Order Direction', 'richie' ) }
						value={ formData.order_direction }
						options={ ORDER_DIRECTION_OPTIONS }
						onChange={ ( value ) =>
							updateField( 'order_direction', value )
						}
					/>
				</div>

				<div className="section-modal-row">
					<SelectControl
						label={ __( 'Max Age', 'richie' ) }
						value={ formData.max_age }
						options={ MAX_AGE_OPTIONS }
						onChange={ ( value ) => updateField( 'max_age', value ) }
					/>
				</div>

				<div className="section-modal-row">
					<label className="components-base-control__label">
						{ __( 'Categories', 'richie' ) }
					</label>
					<div className="category-checkboxes">
						{ categories.map( ( category ) => (
							<CheckboxControl
								key={ category.id }
								label={ category.name }
								checked={ formData.categories.includes(
									category.id
								) }
								onChange={ () =>
									handleCategoryToggle( category.id )
								}
							/>
						) ) }
					</div>
				</div>

				<div className="section-modal-row">
					<TextControl
						label={ __( 'Tags (comma-separated)', 'richie' ) }
						value={ formData.tags }
						onChange={ ( value ) => updateField( 'tags', value ) }
						help={ __(
							'Enter tag slugs separated by commas',
							'richie'
						) }
					/>
				</div>

				<div className="section-modal-row">
					<TextControl
						label={ __( 'Group Title', 'richie' ) }
						value={ formData.list_group_title }
						onChange={ ( value ) =>
							updateField( 'list_group_title', value )
						}
						help={ __(
							'Optional header displayed before section',
							'richie'
						) }
					/>
				</div>

				<div className="section-modal-row">
					<TextControl
						label={ __( 'Background Color', 'richie' ) }
						value={ formData.background_color }
						onChange={ ( value ) =>
							updateField( 'background_color', value )
						}
						help={ __(
							'Hex color without # (e.g., ff0000)',
							'richie'
						) }
					/>
				</div>

				<div className="section-modal-row section-modal-row--checkboxes">
					<CheckboxControl
						label={ __( 'Allow Duplicates', 'richie' ) }
						checked={ formData.allow_duplicates }
						onChange={ ( value ) =>
							updateField( 'allow_duplicates', value )
						}
					/>

					<CheckboxControl
						label={ __( 'Disable Summary', 'richie' ) }
						checked={ formData.disable_summary }
						onChange={ ( value ) =>
							updateField( 'disable_summary', value )
						}
					/>
				</div>
			</div>

			<div className="section-modal-footer">
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Cancel', 'richie' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleSubmit }
					isBusy={ isSaving }
					disabled={ isSaving || ! formData.name }
				>
					{ isEditing
						? __( 'Update Section', 'richie' )
						: __( 'Add Section', 'richie' ) }
				</Button>
			</div>
		</Modal>
	);
}
