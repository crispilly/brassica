package com.flauschcode.broccoli.sync;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.fragment.app.Fragment;

import com.flauschcode.broccoli.R;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

import javax.inject.Inject;

import dagger.android.support.AndroidSupportInjection;

public class SyncFragment extends Fragment {
    @Inject BrassicaSyncService syncService;

    private EditText server;
    private EditText user;
    private EditText password;
    private LinearLayout previewList;
    private TextView status;
    private TextView authStatus;
    private ProgressBar progress;
    private Button syncButton;
    private Button categoryButton;
    private Button previewButton;

    private List<BrassicaSyncService.SyncItem> items = new ArrayList<>();
    private final List<CheckBox> boxes = new ArrayList<>();

    @Override
    public View onCreateView(
            @NonNull LayoutInflater inflater,
            ViewGroup container,
            Bundle state
    ) {
        AndroidSupportInjection.inject(this);

        View root = inflater.inflate(R.layout.fragment_sync, container, false);

        server = root.findViewById(R.id.sync_server);
        user = root.findViewById(R.id.sync_user);
        password = root.findViewById(R.id.sync_password);
        previewList = root.findViewById(R.id.sync_preview_list);
        status = root.findViewById(R.id.sync_status);
        authStatus = root.findViewById(R.id.sync_auth_status);
        progress = root.findViewById(R.id.sync_progress);
        syncButton = root.findViewById(R.id.sync_apply);
        categoryButton = root.findViewById(R.id.sync_select_categories);
        previewButton = root.findViewById(R.id.sync_preview);

        server.setText(syncService.getServer());
        user.setText(syncService.getUser());
        password.setText("");

        updateAuthStatus();

        previewButton.setOnClickListener(v -> loadPreview());
        syncButton.setOnClickListener(v -> applySync());
        categoryButton.setOnClickListener(v -> selectByCategory());

        return root;
    }

    private void loadPreview() {
        setBusy(true);
        progress.setVisibility(View.VISIBLE);
        progress.setIndeterminate(true);
        status.setText(R.string.sync_loading);

        String serverValue = server.getText().toString();
        String userValue = user.getText().toString();
        String passwordValue = password.getText().toString();

        syncService.preview(serverValue, userValue, passwordValue)
                .whenComplete((result, error) ->
                        requireActivity().runOnUiThread(() -> {
                            setBusy(false);
                            progress.setVisibility(View.GONE);
                            previewList.removeAllViews();
                            boxes.clear();
                            updateAuthStatus();

                            if (error != null) {
                                status.setText(errorMessage(error));
                                return;
                            }

                            password.setText("");
                            items = result;
                            status.setText(
                                    result.isEmpty()
                                            ? R.string.sync_all_current
                                            : R.string.sync_preview_ready
                            );

                            for (BrassicaSyncService.SyncItem item : items) {
                                addItem(item);
                            }

                            syncButton.setEnabled(!items.isEmpty());
                            categoryButton.setEnabled(!items.isEmpty());
                        })
                );
    }

    private void addItem(BrassicaSyncService.SyncItem item) {
        CheckBox checkbox = new CheckBox(requireContext());
        boxes.add(checkbox);

        checkbox.setChecked(item.selected);
        checkbox.setText(label(item));
        checkbox.setPadding(8, 8, 8, 8);

        checkbox.setOnCheckedChangeListener((button, checked) -> {
            if (item.action == BrassicaSyncService.Action.CONFLICT && checked) {
                button.setChecked(false);

                new AlertDialog.Builder(requireContext())
                        .setTitle(item.title)
                        .setMessage(R.string.sync_conflict_question)
                        .setNegativeButton(
                                R.string.sync_server_to_app,
                                (dialog, which) -> {
                                    item.action = BrassicaSyncService.Action.DOWNLOAD;
                                    item.selected = true;
                                    button.setText(label(item));
                                    button.setChecked(true);
                                }
                        )
                        .setPositiveButton(
                                R.string.sync_app_to_server,
                                (dialog, which) -> {
                                    item.action = BrassicaSyncService.Action.UPLOAD;
                                    item.selected = true;
                                    button.setText(label(item));
                                    button.setChecked(true);
                                }
                        )
                        .setNeutralButton(android.R.string.cancel, null)
                        .show();
            } else {
                item.selected = checked;
            }
        });

        previewList.addView(checkbox);
    }

    private String label(BrassicaSyncService.SyncItem item) {
        String action;

        if (item.action == BrassicaSyncService.Action.UPLOAD) {
            action = "→ Server";
        } else if (item.action == BrassicaSyncService.Action.DOWNLOAD) {
            action = "→ App";
        } else {
            action = getString(R.string.sync_conflict_label);
        }

        return (item.title == null ? "" : item.title) + "   [" + action + "]";
    }

    private void selectByCategory() {
        Set<String> categorySet = new LinkedHashSet<>();
        for (BrassicaSyncService.SyncItem item : items) {
            categorySet.addAll(item.categories);
        }

        String[] names = categorySet.toArray(new String[0]);
        boolean[] checked = new boolean[names.length];

        new AlertDialog.Builder(requireContext())
                .setTitle(R.string.sync_select_categories)
                .setMultiChoiceItems(
                        names,
                        checked,
                        (dialog, which, selected) -> checked[which] = selected
                )
                .setNegativeButton(android.R.string.cancel, null)
                .setPositiveButton(android.R.string.ok, (dialog, which) -> {
                    Set<String> chosen = new LinkedHashSet<>();
                    for (int i = 0; i < checked.length; i++) {
                        if (checked[i]) {
                            chosen.add(names[i]);
                        }
                    }

                    for (int i = 0; i < items.size(); i++) {
                        BrassicaSyncService.SyncItem item = items.get(i);
                        boolean match = item.categories.stream().anyMatch(chosen::contains);

                        if (match && item.action != BrassicaSyncService.Action.CONFLICT) {
                            item.selected = true;
                            boxes.get(i).setChecked(true);
                        }
                    }
                })
                .show();
    }

    private void applySync() {
        int selectedCount = 0;
        for (BrassicaSyncService.SyncItem item : items) {
            if (item.selected && item.action != BrassicaSyncService.Action.CONFLICT) {
                selectedCount++;
            }
        }

        if (selectedCount == 0) {
            Toast.makeText(
                    requireContext(),
                    R.string.sync_nothing_selected,
                    Toast.LENGTH_SHORT
            ).show();
            return;
        }

        setBusy(true);

        progress.setVisibility(View.VISIBLE);
        progress.setIndeterminate(false);
        progress.setMin(0);
        progress.setMax(selectedCount);
        progress.setProgress(0);

        status.setText(getString(R.string.sync_progress_start, selectedCount));

        syncService.sync(
                server.getText().toString(),
                user.getText().toString(),
                password.getText().toString(),
                items,
                (completed, total, title) ->
                        requireActivity().runOnUiThread(() -> {
                            progress.setIndeterminate(false);
                            progress.setMax(Math.max(total, 1));
                            progress.setProgress(completed);

                            if (completed == 0) {
                                status.setText(getString(R.string.sync_progress_start, total));
                            } else {
                                status.setText(
                                        getString(
                                                R.string.sync_progress,
                                                completed,
                                                total,
                                                title
                                        )
                                );
                            }
                        })
        ).whenComplete((count, error) ->
                requireActivity().runOnUiThread(() -> {
                    setBusy(false);

                    if (error != null) {
                        progress.setVisibility(View.GONE);
                        status.setText(errorMessage(error));
                        Toast.makeText(
                                requireContext(),
                                R.string.sync_failed,
                                Toast.LENGTH_LONG
                        ).show();
                        updateAuthStatus();
                        return;
                    }

                    password.setText("");
                    progress.setProgress(progress.getMax());
                    status.setText(getString(R.string.sync_done, count));
                    updateAuthStatus();

                    new AlertDialog.Builder(requireContext())
                            .setTitle(R.string.sync_done_title)
                            .setMessage(getString(R.string.sync_done, count))
                            .setPositiveButton(android.R.string.ok, (dialog, which) -> {
                                progress.setVisibility(View.GONE);
                                loadPreview();
                            })
                            .setCancelable(false)
                            .show();
                })
        );
    }

    private void updateAuthStatus() {
        if (authStatus == null) {
            return;
        }

        boolean hasKey = syncService.hasSyncKey(
                server == null ? "" : server.getText().toString(),
                user == null ? "" : user.getText().toString()
        );

        authStatus.setText(
                hasKey
                    ? R.string.sync_key_ready
                    : R.string.sync_key_not_ready
        );
    }

    private String errorMessage(Throwable error) {
        Throwable current = error;
        while (current.getCause() != null) {
            current = current.getCause();
        }

        String message = current.getMessage();
        return message == null || message.trim().isEmpty()
                ? getString(R.string.sync_failed)
                : message;
    }

    private void setBusy(boolean busy) {
        if (getView() == null) {
            return;
        }

        previewButton.setEnabled(!busy);
        syncButton.setEnabled(!busy && !items.isEmpty());
        categoryButton.setEnabled(!busy && !items.isEmpty());
        server.setEnabled(!busy);
        user.setEnabled(!busy);
        password.setEnabled(!busy);
    }
}
