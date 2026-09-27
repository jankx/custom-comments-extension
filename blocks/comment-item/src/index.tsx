import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    ToggleControl,
    RangeControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './editor.scss';

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jcc-editor-item' });
    const replyLabel = attributes.replyLabel || __('Thỏa luận', 'jankx');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Cài đặt bình luận', 'jankx')}>
                    <ToggleControl
                        label={__('Hiển thị avatar', 'jankx')}
                        checked={attributes.showAvatar}
                        onChange={(value) => setAttributes({ showAvatar: value })}
                    />
                    <RangeControl
                        label={__('Kích thước avatar (px)', 'jankx')}
                        value={attributes.avatarSize}
                        min={16}
                        max={96}
                        onChange={(value) =>
                            setAttributes({ avatarSize: value || 40 })
                        }
                    />
                    <ToggleControl
                        label={__('Hiển thị thời gian', 'jankx')}
                        checked={attributes.showTime}
                        onChange={(value) => setAttributes({ showTime: value })}
                    />
                    <ToggleControl
                        label={__('Hiển thị lượt thích', 'jankx')}
                        checked={attributes.showLikes}
                        onChange={(value) => setAttributes({ showLikes: value })}
                    />
                    <TextControl
                        label={__('Mẫu số lượt thích', 'jankx')}
                        value={attributes.likesText}
                        onChange={(value) => setAttributes({ likesText: value })}
                    />
                </PanelBody>
                <PanelBody title={__('Phản hồi', 'jankx')} initialOpen={false}>
                    <ToggleControl
                        label={__('Cho phép phản hồi', 'jankx')}
                        checked={attributes.showReply}
                        onChange={(value) => setAttributes({ showReply: value })}
                    />
                    <TextControl
                        label={__('Nhãn nút phản hồi', 'jankx')}
                        value={attributes.replyLabel}
                        onChange={(value) => setAttributes({ replyLabel: value })}
                    />
                    <TextControl
                        label={__('Placeholder phản hồi', 'jankx')}
                        value={attributes.replyPlaceholder}
                        onChange={(value) =>
                            setAttributes({ replyPlaceholder: value })
                        }
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {attributes.showAvatar && (
                    <div className="jcc-editor-item__avatar" />
                )}
                <div className="jcc-editor-item__main">
                    <div className="jcc-editor-item__bubble">
                        <div className="jcc-editor-item__author">
                            {__('Nguyễn Văn A', 'jankx')}
                        </div>
                        <div className="jcc-editor-item__content">
                            {__(
                                'Tour rất đẹp, hướng dẫn viên nhiệt tình. Sẽ quay lại!',
                                'jankx'
                            )}
                        </div>
                    </div>
                    <div className="jcc-editor-item__actions">
                        {attributes.showLikes && (
                            <span className="jcc-editor-item__likes">
                                {__('2 lượt thích', 'jankx')}
                            </span>
                        )}
                        {attributes.showReply && (
                            <span className="jcc-editor-item__reply">
                                {replyLabel}
                            </span>
                        )}
                        {attributes.showTime && (
                            <span className="jcc-editor-item__time">
                                {__('2 tuần', 'jankx')}
                            </span>
                        )}
                    </div>
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
