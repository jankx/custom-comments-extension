import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './editor.scss';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jcc-editor-form' });
    const placeholder =
        attributes.placeholder ||
        __('Hãy viết bình luận của bạn', 'jankx');
    const submitText = attributes.submitText || __('Đăng', 'jankx');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Cài đặt form bình luận', 'jankx')}>
                    <TextControl
                        label={__('Placeholder', 'jankx')}
                        value={attributes.placeholder}
                        onChange={(value) => setAttributes({ placeholder: value })}
                    />
                    <TextControl
                        label={__('Nút gửi', 'jankx')}
                        value={attributes.submitText}
                        onChange={(value) => setAttributes({ submitText: value })}
                    />
                    <ToggleControl
                        label={__('Hiển thị avatar', 'jankx')}
                        checked={attributes.showAvatar}
                        onChange={(value) => setAttributes({ showAvatar: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div className="jcc-editor-form__row">
                    {attributes.showAvatar && (
                        <span className="jcc-editor-form__avatar" />
                    )}
                    <div className="jcc-editor-form__fields">
                        <span className="jcc-editor-form__input">
                            {placeholder}
                        </span>
                    </div>
                </div>
                <div className="jcc-editor-form__footer">
                    <span className="jcc-editor-form__submit">
                        {submitText}
                    </span>
                </div>
            </div>
        </>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => null,
});
