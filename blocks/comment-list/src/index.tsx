import { registerBlockType } from '@wordpress/blocks';
import {
    useBlockProps,
    InspectorControls,
    InnerBlocks,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './editor.scss';

const ITEM_TEMPLATE: [string, Record<string, unknown>][] = [
    ['jankx/comment-item', {}],
];

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'jcc-editor-list' });
    const currentPostId = useSelect(
        (select) => select('core/editor')?.getCurrentPostId?.() || 0,
        []
    );

    useEffect(() => {
        if (!attributes.postId && currentPostId) {
            setAttributes({ postId: currentPostId });
        }
    }, [currentPostId, attributes.postId, setAttributes]);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Cài đặt danh sách bình luận', 'jankx')}>
                    <SelectControl
                        label={__('Thứ tự mặc định', 'jankx')}
                        value={attributes.order}
                        options={[
                            { label: __('Mới nhất', 'jankx'), value: 'newest' },
                            { label: __('Cũ nhất', 'jankx'), value: 'oldest' },
                        ]}
                        onChange={(value) => setAttributes({ order: value })}
                    />
                    <RangeControl
                        label={__(
                            'Số bình luận gốc mỗi trang (0 = tất cả)',
                            'jankx'
                        )}
                        value={attributes.perPage}
                        min={0}
                        max={50}
                        onChange={(value) => setAttributes({ perPage: value || 0 })}
                    />
                    <RangeControl
                        label={__('Độ sâu phản hồi tối đa', 'jankx')}
                        value={attributes.maxDepth}
                        min={1}
                        max={10}
                        onChange={(value) =>
                            setAttributes({ maxDepth: value || 1 })
                        }
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <span className="jcc-editor-list__label">
                    {__('Danh sách bình luận', 'jankx')}
                </span>
                <InnerBlocks template={ITEM_TEMPLATE} templateLock="all" />
            </div>
        </>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => <InnerBlocks.Content />,
});
