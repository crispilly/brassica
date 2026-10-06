package com.flauschcode.broccoli.sync;

import android.os.Bundle;
import android.text.InputType;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.EditText;
import android.widget.LinearLayout;
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

    private EditText server,user,password;
    private LinearLayout previewList;
    private TextView status;
    private Button syncButton, categoryButton;
    private List<BrassicaSyncService.SyncItem> items=new ArrayList<>();
    private final List<CheckBox> boxes=new ArrayList<>();

    @Override public View onCreateView(@NonNull LayoutInflater inflater, ViewGroup container, Bundle state){
        AndroidSupportInjection.inject(this);
        View root=inflater.inflate(R.layout.fragment_sync,container,false);
        server=root.findViewById(R.id.sync_server);user=root.findViewById(R.id.sync_user);password=root.findViewById(R.id.sync_password);previewList=root.findViewById(R.id.sync_preview_list);status=root.findViewById(R.id.sync_status);syncButton=root.findViewById(R.id.sync_apply);categoryButton=root.findViewById(R.id.sync_select_categories);
        server.setText(syncService.getServer());user.setText(syncService.getUser());password.setText(syncService.getPassword());
        root.findViewById(R.id.sync_preview).setOnClickListener(v->loadPreview());
        syncButton.setOnClickListener(v->applySync());categoryButton.setOnClickListener(v->selectByCategory());
        return root;
    }

    private void loadPreview(){
        setBusy(true);status.setText(R.string.sync_loading);
        syncService.preview(server.getText().toString(),user.getText().toString(),password.getText().toString()).whenComplete((result,error)->requireActivity().runOnUiThread(()->{
            setBusy(false);previewList.removeAllViews();boxes.clear();
            if(error!=null){status.setText(error.getCause()!=null?error.getCause().getMessage():error.getMessage());return;}
            items=result;status.setText(result.isEmpty()?R.string.sync_all_current:R.string.sync_preview_ready);
            for(BrassicaSyncService.SyncItem item:items)addItem(item);
            syncButton.setEnabled(!items.isEmpty());categoryButton.setEnabled(!items.isEmpty());
        }));
    }

    private void addItem(BrassicaSyncService.SyncItem item){
        CheckBox cb=new CheckBox(requireContext());boxes.add(cb);
        cb.setChecked(item.selected);cb.setText(label(item));cb.setPadding(8,8,8,8);
        cb.setOnCheckedChangeListener((button,checked)->{
            if(item.action==BrassicaSyncService.Action.CONFLICT && checked){
                button.setChecked(false);
                new AlertDialog.Builder(requireContext()).setTitle(item.title).setMessage(R.string.sync_conflict_question)
                        .setNegativeButton(R.string.sync_server_to_app,(d,w)->{item.action=BrassicaSyncService.Action.DOWNLOAD;item.selected=true;button.setText(label(item));button.setChecked(true);})
                        .setPositiveButton(R.string.sync_app_to_server,(d,w)->{item.action=BrassicaSyncService.Action.UPLOAD;item.selected=true;button.setText(label(item));button.setChecked(true);})
                        .setNeutralButton(android.R.string.cancel,null).show();
            }else item.selected=checked;
        });
        previewList.addView(cb);
    }

    private String label(BrassicaSyncService.SyncItem item){
        String action=item.action==BrassicaSyncService.Action.UPLOAD?"→ Server":item.action==BrassicaSyncService.Action.DOWNLOAD?"→ App":"Konflikt";
        return (item.title==null?"":item.title)+"   ["+action+"]";
    }

    private void selectByCategory(){
        Set<String> categorySet=new LinkedHashSet<>();for(BrassicaSyncService.SyncItem i:items)categorySet.addAll(i.categories);
        String[] names=categorySet.toArray(new String[0]);boolean[] checked=new boolean[names.length];
        new AlertDialog.Builder(requireContext()).setTitle(R.string.sync_select_categories).setMultiChoiceItems(names,checked,(d,w,c)->checked[w]=c)
                .setNegativeButton(android.R.string.cancel,null).setPositiveButton(android.R.string.ok,(d,w)->{
                    Set<String> chosen=new LinkedHashSet<>();for(int i=0;i<checked.length;i++)if(checked[i])chosen.add(names[i]);
                    for(int i=0;i<items.size();i++){BrassicaSyncService.SyncItem item=items.get(i);boolean match=item.categories.stream().anyMatch(chosen::contains);if(match&&item.action!=BrassicaSyncService.Action.CONFLICT){item.selected=true;boxes.get(i).setChecked(true);}}
                }).show();
    }

    private void applySync(){
        setBusy(true);status.setText(R.string.sync_running);
        syncService.sync(server.getText().toString(),user.getText().toString(),password.getText().toString(),items).whenComplete((count,error)->requireActivity().runOnUiThread(()->{
            setBusy(false);if(error!=null){status.setText(error.getCause()!=null?error.getCause().getMessage():error.getMessage());Toast.makeText(requireContext(),R.string.sync_failed,Toast.LENGTH_LONG).show();}
            else {Toast.makeText(requireContext(),getString(R.string.sync_done,count),Toast.LENGTH_LONG).show();loadPreview();}
        }));
    }
    private void setBusy(boolean busy){if(getView()!=null){getView().findViewById(R.id.sync_preview).setEnabled(!busy);syncButton.setEnabled(!busy&&!items.isEmpty());categoryButton.setEnabled(!busy&&!items.isEmpty());}}
}
