/**
 * Correo Argentino — mejoras de UX del checkout para envío a sucursal.
 * (Fork Julian Juan)
 *
 * - Al elegir "envío a sucursal" oculta la sección "enviar a una dirección
 *   diferente" (campos + checkbox): la sucursal se elige con el selector de
 *   provincia/sucursal. Es solo visual (display:none, no disabled) → los valores
 *   se siguen enviando.
 * - Autocompleta el destinatario (nombre, apellido, teléfono y DNI/documento)
 *   desde los datos de facturación.
 * - Muestra un spinner mientras se cargan las sucursales de la provincia.
 * - En envío a domicilio la sección sigue el checkbox "otra dirección". Al salir
 *   de sucursal limpia la dirección que había cargado la sucursal.
 *
 * Señal de "envío a sucursal": el plugin solo renderiza
 * #corrarfo_container_select_provinces en ese modo (no depende de ids de método
 * ni de textos). Usa los IDs del checkout CLÁSICO de WooCommerce; en el checkout
 * por bloques no aplica y se ignora sin romper.
 */
( function () {
	'use strict';

	const $ = window.jQuery;
	if ( ! $ ) {
		return;
	}

	// Config localizada desde PHP (corrarfoBranchUx). camelCase para el linter.
	const cfg = window.corrarfoBranchUx || {};

	const DOCUMENT_KEYS = Array.isArray( cfg.documentKeys )
		? cfg.documentKeys
		: [ 'dni', 'documento', 'document', 'cuit', 'cuil', 'nif' ];
	const SPINNER_TEXT = cfg.spinnerText || 'Cargando sucursales…';
	const HIDDEN_CLASS = 'corrarfo-branch-hidden';
	const SPINNER_ID = 'corrarfo-branch-spinner';
	// Campos de dirección que completa la sucursal (se limpian al salir de ella).
	const ADDRESS_FIELDS = [
		'company',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
	];

	let wasBranch = false;

	function isBranchShipping() {
		return !! document.querySelector(
			'#corrarfo_container_select_provinces'
		);
	}

	// ---- Visibilidad de la sección "otra dirección" -----------------------

	function setCheckboxVisible( visible ) {
		const heading = document.getElementById( 'ship-to-different-address' );
		if ( heading ) {
			heading.style.display = visible ? '' : 'none';
		}
	}

	function shippingFollowsCheckbox() {
		const shipping = document.querySelector( '.shipping_address' );
		if ( ! shipping ) {
			return;
		}
		const checkbox = document.getElementById(
			'ship-to-different-address-checkbox'
		);
		shipping.style.display = checkbox && checkbox.checked ? '' : 'none';
	}

	function resetShippingAddress() {
		ADDRESS_FIELDS.forEach( function ( key ) {
			const $field = $( '#shipping_' + key );
			if ( $field.length && $field.val() ) {
				$field.val( '' ).trigger( 'change' );
			}
		} );
	}

	function applyVisibility() {
		const branch = isBranchShipping();
		const shipping = document.querySelector( '.shipping_address' );
		setCheckboxVisible( ! branch );

		if ( shipping ) {
			if ( branch ) {
				// Clase con display:none !important: WooCommerce o el toggle
				// legacy podrían re-mostrar la sección con display inline; el
				// !important gana sin depender del orden de los handlers.
				shipping.classList.add( HIDDEN_CLASS );
				shipping.style.display = '';
			} else {
				shipping.classList.remove( HIDDEN_CLASS );
				shippingFollowsCheckbox();
			}
		}

		// Al pasar de sucursal a domicilio, limpiar la dirección de la sucursal
		// para que el cliente no envíe esos datos por error.
		if ( wasBranch && ! branch ) {
			resetShippingAddress();
		}
		wasBranch = branch;
	}

	// ---- Autocompletar el destinatario desde facturación ------------------

	function copyField( key ) {
		try {
			const $from = $( '#billing_' + key );
			const $to = $( '#shipping_' + key );
			if ( ! $from.length || ! $to.length ) {
				return;
			}
			const value = $from.val();
			if ( value === undefined || value === null || value === '' ) {
				return;
			}
			if ( $to.val() !== value ) {
				$to.val( value ).trigger( 'change' );
			}
		} catch ( e ) {
			// Campo inexistente o con otro nombre: se ignora a propósito.
		}
	}

	function autofillRecipient() {
		if ( ! isBranchShipping() ) {
			return;
		}
		copyField( 'first_name' );
		copyField( 'last_name' );
		copyField( 'phone' );
		DOCUMENT_KEYS.forEach( copyField );
	}

	// ---- Spinner "Cargando sucursales…" -----------------------------------

	function isProvinceRequest( settings ) {
		const data = settings && settings.data;
		return (
			typeof data === 'string' &&
			data.indexOf( 'corrarfo_update_province' ) !== -1
		);
	}

	function showSpinner() {
		if ( document.getElementById( SPINNER_ID ) ) {
			return;
		}
		const anchor =
			document.querySelector(
				'[id^="corrarfo_container_select_"]:not(#corrarfo_container_select_provinces)'
			) ||
			document.getElementById( 'corrarfo_container_select_provinces' );
		if ( ! anchor || ! anchor.parentNode ) {
			return;
		}
		const el = document.createElement( 'div' );
		el.id = SPINNER_ID;
		el.className = 'corrarfo-branch-spinner';
		el.innerHTML =
			'<span class="corrarfo-spinner" aria-hidden="true"></span> ';
		el.appendChild( document.createTextNode( SPINNER_TEXT ) );
		anchor.parentNode.insertBefore( el, anchor );
	}

	function hideSpinner() {
		const el = document.getElementById( SPINNER_ID );
		if ( el && el.parentNode ) {
			el.parentNode.removeChild( el );
		}
	}

	// ---- Wire-up ----------------------------------------------------------

	function apply() {
		applyVisibility();
		autofillRecipient();
	}

	$( document.body ).on( 'updated_checkout', apply );
	$( document.body ).on(
		'change',
		'#ship-to-different-address-checkbox',
		applyVisibility
	);
	$( document.body ).on(
		'change keyup',
		'[id^="billing_"]',
		autofillRecipient
	);

	$( document ).on( 'ajaxSend', function ( event, jqxhr, settings ) {
		if ( isProvinceRequest( settings ) ) {
			showSpinner();
		}
	} );
	$( document ).on( 'ajaxComplete', function ( event, jqxhr, settings ) {
		if ( isProvinceRequest( settings ) ) {
			hideSpinner();
		}
	} );

	$( apply );
} )();
