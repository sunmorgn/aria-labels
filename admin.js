'use strict'

// Get settings passed from PHP. Fallback to defaults if the object is not available.
const { moveToAdvanced: MOVE_CONTROLS_TO_ADVANCED_PANEL, allowedBlocks } = window.ariaLabelsSettings || { moveToAdvanced: true, allowedBlocks: [] };

// Add the switch and a text field to a new "Aria Labels" section
wp.hooks.addFilter(
	'blocks.registerBlockType',
	'aria-labels/add-controls',
	(settings, name) => {
		// If the block is not in our allowed list, do nothing.
		if (!allowedBlocks.includes(name)) {
			return settings;
		}

		// Check if the block has native support for aria-label.
		// Some blocks (like core/group) declare support but don't render a UI control,
		// so we exclude them from the native support check.
		const blocksWithBrokenNativeSupport = ['core/group'];
		const hasNativeAriaLabelSupport = settings.supports?.ariaLabel === true &&
			!blocksWithBrokenNativeSupport.includes(name);

		// Define the new attributes to be added to the block.
		const newAttributes = {
			ariaHidden: {
				type: 'boolean',
				default: false,
			},
		};

		// Only add our custom ariaLabel attribute if the block doesn't have native support.
		if ( ! hasNativeAriaLabelSupport ) {
			newAttributes.ariaLabel = {
				type: 'string',
				default: '',
			};
		}

		settings.attributes = { ...settings.attributes, ...newAttributes };
		// Store the original edit function
		const oldEdit = settings.edit

		const { createElement, Fragment } = wp.element;
		const { InspectorControls, InspectorAdvancedControls } = wp.blockEditor;
		const { PanelBody, ToggleControl, TextControl } = wp.components;
		const { createHigherOrderComponent } = wp.compose;

		// Replace the edit function with a higher order component
		settings.edit = createHigherOrderComponent(
			(BlockEdit) => (props) => {
				// Create a ToggleControl for the ariaHidden attribute
				const ariaHiddenToggle = createElement(
					ToggleControl,
					{
						label: 'Aria Hidden',
						checked: props.attributes.ariaHidden,
						onChange: (newValue) => {
							// Update the ariaHidden attribute when the ToggleControl is toggled
							props.setAttributes({
								ariaHidden: newValue,
							});
						},
					}
				);

				// Create a TextControl for the ariaLabel attribute
				let ariaLabelInput = null;

				// Only show our custom control if the block doesn't have native support.
				if ( ! hasNativeAriaLabelSupport ) {
					ariaLabelInput = createElement(
						TextControl,
						{
							label: 'Aria Label',
							value: props.attributes.ariaLabel,
							onChange: (newValue) => {
								// Update the ariaLabel attribute when the TextControl value changes
								props.setAttributes({
									ariaLabel: newValue,
								});
							},
						}
					);
				}

				let inspectorPanel;

				if (MOVE_CONTROLS_TO_ADVANCED_PANEL) {
					// Place controls into the 'Advanced' panel.
					inspectorPanel = createElement(
						InspectorAdvancedControls,
						null, // No props
						ariaHiddenToggle,
						ariaLabelInput // This will be null and not render if native support exists.
					);
				} else {
					// Place controls in their own dedicated panel.
					const panelBody = createElement(
						PanelBody,
						{ title: 'Aria Labels', initialOpen: true },
						ariaHiddenToggle,
						ariaLabelInput // This will be null and not render if native support exists.
					);
					inspectorPanel = createElement(InspectorControls, null, panelBody);
				}

				// Return the original BlockEdit component along with the new InspectorControls
				return createElement(
					Fragment,
					null, // No props
					createElement(BlockEdit, props),
					inspectorPanel
				);
			},
			'withAriaControls'
		)(oldEdit)

		// Store the original save function
		const oldSave = settings.save;

		// Only modify the save function if it exists (e.g., dynamic blocks don't have one).
		if (oldSave) {
			// Replace the save function to include the ARIA labels
			settings.save = (props) => {
				const { attributes } = props;
				const { ariaHidden, ariaLabel } = attributes;
				// Get the original element from the save function
				const originalElement = oldSave(props);

				// If there's no element or no attributes to add, do nothing.
				if (!originalElement || (!ariaHidden && !ariaLabel)) {
					return originalElement;
				}

				const newAriaProps = {};
				if (ariaHidden) newAriaProps['aria-hidden'] = 'true';
				if (ariaLabel) newAriaProps['aria-label'] = ariaLabel;

				// Helper function to find all occurrences of a tag recursively.
				const findTags = (element, tagName) => {
					const found = [];
					const traverse = (el) => {
						if (!el || typeof el !== 'object') {
							return;
						}
						if (el.type === tagName) {
							found.push(el);
						}
						if (el.props && el.props.children) {
							wp.element.Children.forEach(el.props.children, traverse);
						}
					};
					traverse(element);
					return found;
				};

				// Helper function to apply props to the first occurrence of a tag.
				const applyPropsToFirstTag = (element, tagName, propsToApply) => {
					let applied = false;
					const traverse = (el) => {
						if (applied || !el || typeof el !== 'object') {
							return el;
						}
						if (el.type === tagName && !applied) {
							applied = true;
							return wp.element.cloneElement(el, propsToApply);
						}
						if (el.props && el.props.children) {
							const newChildren = wp.element.Children.map(el.props.children, traverse);
							if (newChildren !== el.props.children) {
								return wp.element.cloneElement(el, {}, newChildren);
							}
						}
						return el;
					};
					return traverse(element);
				};

				const links = findTags(originalElement, 'a');

				// If there is exactly one link in the block, apply the ARIA attributes to it.
				if (links.length === 1) {
					return applyPropsToFirstTag(originalElement, 'a', newAriaProps);
				}

				// If there are no links, but it's an image block, apply to the image.
				if (links.length === 0 && name === 'core/image') {
					return applyPropsToFirstTag(originalElement, 'img', newAriaProps);
				}

				// Default behavior for other blocks: apply to the wrapper.
				return wp.element.cloneElement(originalElement, { ...originalElement.props, ...newAriaProps });
			};
		}

		// Return the modified block settings
		return settings
	}
)
