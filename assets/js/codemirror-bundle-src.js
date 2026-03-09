import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLineGutter } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { searchKeymap } from '@codemirror/search';
import {
  syntaxHighlighting,
  defaultHighlightStyle,
  foldGutter,
  codeFolding,
  indentOnInput,
  bracketMatching,
} from '@codemirror/language';
import { autocompletion, completionKeymap } from '@codemirror/autocomplete';
import { javascript } from '@codemirror/lang-javascript';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { php } from '@codemirror/lang-php';

window.MFMCodeMirror = {
  EditorState,
  EditorView,
  keymap,
  lineNumbers,
  highlightActiveLineGutter,
  defaultKeymap,
  history,
  historyKeymap,
  indentWithTab,
  searchKeymap,
  syntaxHighlighting,
  defaultHighlightStyle,
  foldGutter,
  codeFolding,
  indentOnInput,
  bracketMatching,
  autocompletion,
  completionKeymap,
  javascript,
  css,
  html,
  php,
};
