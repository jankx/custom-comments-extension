import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from '../block.json';
import './editor.scss';

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['jankx/comments-sort', {}],
    ['jankx/comment-form', {}],
    ['jankx/comment-list', {}],
];

function Edit() {
    const blockProps = useBlockProps({ className: 'jcc-editor-comments' });

    return (
        <div {...blockProps}>
            <span className="jcc-editor-comments__label">
                {__('Bình luận', 'jankx')}
            </span>
            <InnerBlocks template={TEMPLATE} templateLock={false} />
        </div>
    );
}

registerBlockType(metadata.name, {
    ...metadata,
    edit: Edit,
    save: () => <InnerBlocks.Content />,
});
