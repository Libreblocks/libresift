/**
 * LibreSift blocks: Filter and Clear filters (rendered on the server).
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { Button, PanelBody, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import filter from '../../../blocks/filter/block.json';
import reset from '../../../blocks/reset/block.json';

let filtersPromise;

function useFilters() {
	const [ filters, setFilters ] = useState( null );

	useEffect( () => {
		filtersPromise = filtersPromise || apiFetch( { path: '/libreblocks/v1/sift/filters' } );
		filtersPromise.then( setFilters ).catch( () => setFilters( [] ) );
	}, [] );

	return filters;
}

const manageUrl = `${ window.location.origin }/wp-admin/admin.php?page=libresift`;

function FilterEdit( { attributes, setAttributes, name } ) {
	const filters = useFilters();
	const props = useBlockProps();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Filter', 'libresift' ) }>
					{ ! filters ? (
						<Spinner />
					) : (
						<SelectControl
							label={ __( 'Filter', 'libresift' ) }
							help={ __( 'Create filters in LibreSift → Filters.', 'libresift' ) }
							value={ String( attributes.filterId || '' ) }
							options={ [ { value: '', label: __( '— Choose —', 'libresift' ) }, ...filters.map( ( item ) => ( { value: String( item.value ), label: item.label } ) ) ] }
							onChange={ ( value ) => setAttributes( { filterId: Number( value ) || 0 } ) }
							__nextHasNoMarginBottom
						/>
					) }
					<ToggleControl label={ __( 'Show the title', 'libresift' ) } checked={ attributes.showLabel } onChange={ set( 'showLabel' ) } __nextHasNoMarginBottom />
					<SelectControl
						label={ __( 'When to update the results', 'libresift' ) }
						value={ attributes.apply }
						options={ [
							{ value: 'instant', label: __( 'Right away', 'libresift' ) },
							{ value: 'button', label: __( 'When clicking a button', 'libresift' ) },
						] }
						onChange={ set( 'apply' ) }
						__nextHasNoMarginBottom
					/>
					{ attributes.apply === 'button' && <TextControl label={ __( 'Button text', 'libresift' ) } value={ attributes.buttonText } placeholder={ __( 'Filter', 'libresift' ) } onChange={ set( 'buttonText' ) } __nextHasNoMarginBottom /> }
					<Button variant="link" href={ manageUrl } target="_blank">
						{ __( 'Manage filters', 'libresift' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			{ attributes.filterId ? (
				<ServerSideRender block={ name } attributes={ attributes } skipBlockSupportAttributes />
			) : (
				<div className="ls-notice">
					{ filters && filters.length === 0
						? __( 'Create your first filter in LibreSift → Filters, then choose it here.', 'libresift' )
						: __( 'Choose a filter in the sidebar.', 'libresift' ) }
				</div>
			) }
		</div>
	);
}

function ResetEdit( { attributes, setAttributes } ) {
	const props = useBlockProps();

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Clear filters', 'libresift' ) }>
					<TextControl label={ __( 'Text', 'libresift' ) } value={ attributes.text } placeholder={ __( 'Clear filters', 'libresift' ) } onChange={ ( text ) => setAttributes( { text } ) } __nextHasNoMarginBottom />
				</PanelBody>
			</InspectorControls>
			<span className="ls-reset">{ attributes.text || __( 'Clear filters', 'libresift' ) }</span>
		</div>
	);
}

registerBlockType( filter.name, { ...filter, edit: FilterEdit, save: () => null } );
registerBlockType( reset.name, { ...reset, edit: ResetEdit, save: () => null } );
