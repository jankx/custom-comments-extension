import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './editor.scss';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jcc-editor-counter' });
    const format = attributes.format || __('%d bình luận', 'jankx');
    const preview = format.replace('%d', '123');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Cài đặt số bình luận', 'jankx')}>
                    <TextControl
                        label={__('Mẫu hiển thị (%d = số lượng)', 'jankx')}
                        value={attributes.format}
                        placeholder={__('%d bình luận', 'jankx')}
                        onChange={(value) => setAttributes({ format: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <span {...blockProps}>{preview}</span>
        </>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => null,
});
