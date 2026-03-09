import { EditorState, Compartment } from '@codemirror/state';
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
import { oneDark } from '@codemirror/theme-one-dark';
import { monokai } from '@fsegurai/codemirror-theme-monokai';
import { solarizedDark } from '@fsegurai/codemirror-theme-solarized-dark';
import { tokyoNightStorm } from '@fsegurai/codemirror-theme-tokyo-night-storm';
import { nord } from '@fsegurai/codemirror-theme-nord';

window.MFMCodeMirror = {
  EditorState,
  Compartment,
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
  oneDark,
  monokai,
  solarizedDark,
  tokyoNightStorm,
  nord,
};
