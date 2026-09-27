import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './editor.scss';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jcc-editor-sort' });
    const label =
        attributes.order === 'oldest'
            ? __('Cũ nhất', 'jankx')
            : __('Mới nhất', 'jankx');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Cài đặt sắp xếp', 'jankx')}>
                    <SelectControl
                        label={__('Thứ tự mặc định', 'jankx')}
                        value={attributes.order}
                        options={[
                            { label: __('Mới nhất', 'jankx'), value: 'newest' },
                            { label: __('Cũ nhất', 'jankx'), value: 'oldest' },
                        ]}
                        onChange={(value) => setAttributes({ order: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <span className="jcc-editor-sort__pill">
                    {label}
                    <span className="jcc-editor-sort__caret" aria-hidden="true">
                        ⇅
                    </span>
                </span>
            </div>
        </>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => null,
});
